<?php

namespace Kanboard\Plugin\TeamWork\Model;

use Kanboard\Core\Base;

/**
 * TaskAssigneeModel
 *
 * Multi-assignee CRUD for teamwork_task_assignees table.
 * Handles individual user assignment, group expansion, team expansion,
 * source tracking (source_type/source_id), and owner_id sync.
 */
class TaskAssigneeModel extends Base
{
    const TABLE = 'teamwork_task_assignees';
    const TEAMS_TABLE = 'teamwork_teams';
    const TEAM_MEMBERS_TABLE = 'teamwork_team_members';
    const TASK_TEAMS_TABLE = 'teamwork_task_teams';

    /**
     * Whether the task<->team table has been verified this request.
     * @var bool
     */
    private static $taskTeamsTableChecked = false;

    /**
     * Defensive safety net: ensure the task<->team association table exists.
     *
     * The table is normally created by the schema migration (version_3). On some
     * hosts a plugin upgrade may not re-run migrations cleanly (or a migration may
     * have been rolled back), which would make every team-on-task query silently
     * do nothing. This creates the table on demand if it is missing so the feature
     * always works. It runs at most once per request.
     *
     * The portable DDL deliberately omits foreign keys to avoid cross-engine
     * column-type mismatches; referential cleanup is handled in PHP
     * (removeTeam / removeTeamFromAllTasks).
     *
     * @return void
     */
    private function ensureTaskTeamsTable(): void
    {
        if (self::$taskTeamsTableChecked) {
            return;
        }
        self::$taskTeamsTableChecked = true;

        try {
            $this->db->getConnection()->exec(
                'CREATE TABLE IF NOT EXISTS ' . self::TASK_TEAMS_TABLE . ' (
                    task_id INTEGER NOT NULL,
                    team_id INTEGER NOT NULL,
                    created_at INTEGER NOT NULL DEFAULT 0,
                    PRIMARY KEY (task_id, team_id)
                )'
            );
        } catch (\Throwable $e) {
            // If creation is not possible (e.g. insufficient privileges) leave it
            // to the migration; queries will simply find no linked teams.
        }
    }

    /**
     * Get all assignees for a task, joined with user info, ordered by position.
     *
     * @param int $taskId
     * @return array
     */
    public function getAssigneesForTask(int $taskId): array
    {
        return $this->db
            ->table(self::TABLE)
            ->columns(
                self::TABLE . '.*',
                'users.username',
                'users.name',
                'users.email'
            )
            ->join('users', 'id', 'user_id', self::TABLE)
            ->eq(self::TABLE . '.task_id', $taskId)
            ->asc(self::TABLE . '.position')
            ->findAll();
    }

    /**
     * Add a single assignee to a task.
     *
     * Returns true on success, false if user is already assigned (UNIQUE violation).
     * Automatically syncs owner_id after successful insert.
     *
     * @param int         $taskId
     * @param int         $userId
     * @param string|null $role       Optional role label
     * @param string      $sourceType 'user', 'group', or 'team'
     * @param int|null    $sourceId   NULL for direct, group_id or team_id for expansion
     * @return bool
     */
    public function addAssignee(int $taskId, int $userId, ?string $role = null, string $sourceType = 'user', ?int $sourceId = null): bool
    {
        $lastRow = $this->db
            ->table(self::TABLE)
            ->eq('task_id', $taskId)
            ->desc('position')
            ->findOneColumn('position');

        $position = $lastRow !== null && $lastRow !== false ? (int) $lastRow + 1 : 1;

        $result = $this->db->table(self::TABLE)->insert([
            'task_id'     => $taskId,
            'user_id'     => $userId,
            'role'        => $role,
            'position'    => $position,
            'source_type' => $sourceType,
            'source_id'   => $sourceId,
            'created_at'  => time(),
        ]);

        if ($result === false) {
            return false;
        }

        $this->syncPrimaryAssignee($taskId);

        return true;
    }

    /**
     * Remove a single assignee by their assignee record id.
     *
     * Both $assigneeId and $taskId are required for security (prevents
     * cross-task deletion). Resequences positions and syncs owner_id after removal.
     *
     * @param int $assigneeId
     * @param int $taskId
     * @return bool
     */
    public function removeAssignee(int $assigneeId, int $taskId): bool
    {
        // Capture the user id before deletion so the owner sync knows whether
        // the removed assignee was the current owner.
        $removedUserId = $this->db
            ->table(self::TABLE)
            ->eq('id', $assigneeId)
            ->eq('task_id', $taskId)
            ->findOneColumn('user_id');

        $result = $this->db
            ->table(self::TABLE)
            ->eq('id', $assigneeId)
            ->eq('task_id', $taskId)
            ->remove();

        if (!$result) {
            return false;
        }

        $this->resequencePositions($taskId);
        $this->syncPrimaryAssignee($taskId, $removedUserId ? [(int) $removedUserId] : []);

        return true;
    }

    /**
     * Expand a Kanboard group onto a task.
     *
     * Fetches group members via groupMemberModel and adds each as an assignee
     * with source_type='group' and source_id=$groupId. Duplicates are silently skipped.
     *
     * @param int $taskId
     * @param int $groupId
     * @return int Count of newly added members
     */
    public function addGroup(int $taskId, int $groupId): int
    {
        $members = $this->groupMemberModel->getMembers($groupId);
        $added = 0;

        foreach ($members as $member) {
            if ($this->addAssignee($taskId, (int) $member['id'], null, 'group', $groupId)) {
                $added++;
            }
        }

        return $added;
    }

    /**
     * Link a plugin-defined team to a task (live link).
     *
     * Records the task<->team association in teamwork_task_teams so the team is
     * visible on the card even when it has no members yet. Also materializes the
     * team's CURRENT members as assignee rows (source_type='team', source_id=$teamId)
     * so the rest of the plugin (owner sync, notifications, filters, board avatars)
     * keeps working. Idempotent: safe to call repeatedly; it also re-syncs any
     * members that are missing.
     *
     * @param int $taskId
     * @param int $teamId
     * @return int Count of newly added members
     */
    public function addTeam(int $taskId, int $teamId): int
    {
        $this->ensureTaskTeamsTable();

        // Record the association (idempotent)
        $exists = $this->db
            ->table(self::TASK_TEAMS_TABLE)
            ->eq('task_id', $taskId)
            ->eq('team_id', $teamId)
            ->exists();

        if (!$exists) {
            $this->db->table(self::TASK_TEAMS_TABLE)->insert([
                'task_id'    => $taskId,
                'team_id'    => $teamId,
                'created_at' => time(),
            ]);
        }

        // Materialize current members
        $members = $this->db
            ->table(self::TEAM_MEMBERS_TABLE)
            ->columns(self::TEAM_MEMBERS_TABLE . '.user_id')
            ->join('users', 'id', 'user_id', self::TEAM_MEMBERS_TABLE)
            ->eq(self::TEAM_MEMBERS_TABLE . '.team_id', $teamId)
            ->findAll();

        $added = 0;

        foreach ($members as $member) {
            if ($this->addAssignee($taskId, (int) $member['user_id'], null, 'team', $teamId)) {
                $added++;
            }
        }

        return $added;
    }

    /**
     * Get the teams linked to a task, joined with team name.
     *
     * Returns every linked team — including teams that currently have no
     * members — so an empty team still renders on the card.
     *
     * @param int $taskId
     * @return array Array of ['team_id', 'name'] rows ordered by name
     */
    public function getTaskTeams(int $taskId): array
    {
        $this->ensureTaskTeamsTable();

        return $this->db
            ->table(self::TASK_TEAMS_TABLE)
            ->columns(
                self::TASK_TEAMS_TABLE . '.team_id',
                self::TEAMS_TABLE . '.name'
            )
            ->join(self::TEAMS_TABLE, 'id', 'team_id', self::TASK_TEAMS_TABLE)
            ->eq(self::TASK_TEAMS_TABLE . '.task_id', $taskId)
            ->asc(self::TEAMS_TABLE . '.name')
            ->findAll();
    }

    /**
     * Build the structured assignment view for rendering (PHP templates + AJAX).
     *
     * Returns three buckets:
     *   - individuals: assignee rows with source_type='user'
     *   - teams:       one entry per linked team {id, name, members[]} — includes empty teams
     *   - groups:      one entry per Kanboard group present {id, name, members[]}
     *
     * Team/group entries carry the real team/group NAME (not a member's name).
     *
     * @param int $taskId
     * @return array
     */
    public function getAssignmentView(int $taskId): array
    {
        $assignees = $this->getAssigneesForTask($taskId);

        $individuals = [];
        $teamMembers = [];   // team_id => [rows]
        $groupMembers = [];  // group_id => [rows]

        foreach ($assignees as $a) {
            if ($a['source_type'] === 'team') {
                $teamMembers[(int) $a['source_id']][] = $a;
            } elseif ($a['source_type'] === 'group') {
                $groupMembers[(int) $a['source_id']][] = $a;
            } else {
                $individuals[] = $a;
            }
        }

        // Teams come from the association table so empty teams are included.
        $teams = [];
        foreach ($this->getTaskTeams($taskId) as $team) {
            $teamId = (int) $team['team_id'];
            $teams[] = [
                'id'      => $teamId,
                'name'    => $team['name'],
                'members' => $teamMembers[$teamId] ?? [],
            ];
        }

        // Groups are only known through their materialized member rows.
        $groups = [];
        foreach ($groupMembers as $groupId => $members) {
            $group = $this->groupModel->getById($groupId);
            $groups[] = [
                'id'      => $groupId,
                'name'    => !empty($group['name']) ? $group['name'] : t('Group'),
                'members' => $members,
            ];
        }

        return [
            'individuals' => $individuals,
            'teams'       => $teams,
            'groups'      => $groups,
        ];
    }

    /**
     * Remove all assignees that came from a specific group.
     *
     * Deletes all rows where task_id, source_type='group', and source_id=$groupId match.
     * Resequences positions and syncs owner_id after removal.
     *
     * @param int $taskId
     * @param int $groupId
     * @return int Count of deleted rows
     */
    public function removeGroup(int $taskId, int $groupId): int
    {
        $removedUserIds = array_map('intval', $this->db
            ->table(self::TABLE)
            ->eq('task_id', $taskId)
            ->eq('source_type', 'group')
            ->eq('source_id', $groupId)
            ->findAllByColumn('user_id'));

        $count = count($removedUserIds);

        if ($count > 0) {
            $this->db
                ->table(self::TABLE)
                ->eq('task_id', $taskId)
                ->eq('source_type', 'group')
                ->eq('source_id', $groupId)
                ->remove();

            $this->resequencePositions($taskId);
            $this->syncPrimaryAssignee($taskId, $removedUserIds);
        }

        return $count;
    }

    /**
     * Remove all assignees that came from a specific team.
     *
     * Deletes all rows where task_id, source_type='team', and source_id=$teamId match.
     * Resequences positions and syncs owner_id after removal.
     *
     * @param int $taskId
     * @param int $teamId
     * @return int Count of deleted rows
     */
    public function removeTeam(int $taskId, int $teamId): int
    {
        $this->ensureTaskTeamsTable();

        // Drop the task<->team association first
        $this->db
            ->table(self::TASK_TEAMS_TABLE)
            ->eq('task_id', $taskId)
            ->eq('team_id', $teamId)
            ->remove();

        $removedUserIds = array_map('intval', $this->db
            ->table(self::TABLE)
            ->eq('task_id', $taskId)
            ->eq('source_type', 'team')
            ->eq('source_id', $teamId)
            ->findAllByColumn('user_id'));

        $count = count($removedUserIds);

        if ($count > 0) {
            $this->db
                ->table(self::TABLE)
                ->eq('task_id', $taskId)
                ->eq('source_type', 'team')
                ->eq('source_id', $teamId)
                ->remove();

            $this->resequencePositions($taskId);
            $this->syncPrimaryAssignee($taskId, $removedUserIds);
        }

        return $count;
    }

    /**
     * Propagate a newly-added team member to every task the team is linked to.
     *
     * Keeps live-linked teams in sync: when a user joins a team, they appear on
     * all cards that team is assigned to. Duplicates are silently skipped.
     *
     * @param int $teamId
     * @param int $userId
     * @return void
     */
    public function syncTeamMemberAdded(int $teamId, int $userId): void
    {
        $this->ensureTaskTeamsTable();

        $taskIds = $this->db
            ->table(self::TASK_TEAMS_TABLE)
            ->eq('team_id', $teamId)
            ->findAllByColumn('task_id');

        foreach ($taskIds as $taskId) {
            $this->addAssignee((int) $taskId, $userId, null, 'team', $teamId);
        }
    }

    /**
     * Detach a team from every task: drop all associations and all materialized
     * member rows for the team, then resync affected tasks. Used when a team is
     * deleted so no orphaned assignees linger on cards.
     *
     * @param int $teamId
     * @return void
     */
    public function removeTeamFromAllTasks(int $teamId): void
    {
        $this->ensureTaskTeamsTable();

        // Capture (task_id, user_id) pairs before deletion so each task's
        // owner sync knows which users were removed.
        $rows = $this->db
            ->table(self::TABLE)
            ->columns('task_id', 'user_id')
            ->eq('source_type', 'team')
            ->eq('source_id', $teamId)
            ->findAll();

        $removedByTask = [];
        foreach ($rows as $row) {
            $removedByTask[(int) $row['task_id']][] = (int) $row['user_id'];
        }

        $this->db
            ->table(self::TABLE)
            ->eq('source_type', 'team')
            ->eq('source_id', $teamId)
            ->remove();

        $this->db
            ->table(self::TASK_TEAMS_TABLE)
            ->eq('team_id', $teamId)
            ->remove();

        foreach ($removedByTask as $taskId => $removedUserIds) {
            $this->resequencePositions($taskId);
            $this->syncPrimaryAssignee($taskId, $removedUserIds);
        }
    }

    /**
     * Propagate a removed team member off every task the team is linked to.
     *
     * Only removes the team-sourced row for this user (a direct user assignment
     * is preserved). If another team linked to the same task still contains the
     * user, they are re-linked under that team so they stay visible.
     *
     * @param int $teamId
     * @param int $userId
     * @return void
     */
    public function syncTeamMemberRemoved(int $teamId, int $userId): void
    {
        $this->ensureTaskTeamsTable();

        $taskIds = $this->db
            ->table(self::TASK_TEAMS_TABLE)
            ->eq('team_id', $teamId)
            ->findAllByColumn('task_id');

        foreach ($taskIds as $taskId) {
            $taskId = (int) $taskId;

            $removed = $this->db
                ->table(self::TABLE)
                ->eq('task_id', $taskId)
                ->eq('user_id', $userId)
                ->eq('source_type', 'team')
                ->eq('source_id', $teamId)
                ->remove();

            if (!$removed) {
                continue;
            }

            $otherTeamId = $this->findOtherTeamWithMember($taskId, $teamId, $userId);
            if ($otherTeamId !== null) {
                // Still on the card via another linked team: re-link and keep
                // the assignment untouched.
                $this->addAssignee($taskId, $userId, null, 'team', $otherTeamId);
                $this->resequencePositions($taskId);
                continue;
            }

            $this->resequencePositions($taskId);
            $this->syncPrimaryAssignee($taskId, [$userId]);
        }
    }

    /**
     * Find another team (besides $excludeTeamId) linked to $taskId that still
     * has $userId as a member.
     *
     * @param int $taskId
     * @param int $excludeTeamId
     * @param int $userId
     * @return int|null
     */
    private function findOtherTeamWithMember(int $taskId, int $excludeTeamId, int $userId): ?int
    {
        $result = $this->db
            ->table(self::TASK_TEAMS_TABLE)
            ->columns(self::TASK_TEAMS_TABLE . '.team_id')
            ->join(self::TEAM_MEMBERS_TABLE, 'team_id', 'team_id', self::TASK_TEAMS_TABLE)
            ->eq(self::TASK_TEAMS_TABLE . '.task_id', $taskId)
            ->eq(self::TEAM_MEMBERS_TABLE . '.user_id', $userId)
            ->neq(self::TASK_TEAMS_TABLE . '.team_id', $excludeTeamId)
            ->findOneColumn(self::TASK_TEAMS_TABLE . '.team_id');

        return ($result !== null && $result !== false) ? (int) $result : null;
    }

    /**
     * Sync the primary assignee to Kanboard's tasks.owner_id — conservatively.
     *
     * The assignment must be STABLE: editing a team or adding assignees never
     * steals an existing assignment. Rules, in order:
     *
     *   1. Current owner is still one of the task's assignees -> keep them.
     *   2. Current owner is set but is NOT an assignee and was NOT just removed
     *      (i.e. the owner is managed outside this plugin, e.g. Kanboard's own
     *      assignee dropdown) -> leave them alone.
     *   3. Otherwise (no owner, or the owner was just removed) -> promote the
     *      first remaining assignee by position, or 0 when none are left.
     *
     * The second argument (false) to update() is CRITICAL — it disables event
     * firing to prevent event loops.
     *
     * @param int   $taskId
     * @param int[] $removedUserIds User ids removed by the calling operation
     * @return void
     */
    public function syncPrimaryAssignee(int $taskId, array $removedUserIds = []): void
    {
        $currentOwner = (int) $this->db
            ->table('tasks')
            ->eq('id', $taskId)
            ->findOneColumn('owner_id');

        $assigneeIds = array_map('intval', $this->db
            ->table(self::TABLE)
            ->eq('task_id', $taskId)
            ->asc('position')
            ->findAllByColumn('user_id'));

        // Rule 1: owner is still assigned — never steal the assignment.
        if ($currentOwner !== 0 && in_array($currentOwner, $assigneeIds, true)) {
            return;
        }

        // Rule 2: owner set outside the plugin — don't overwrite them.
        $removedUserIds = array_map('intval', $removedUserIds);
        if ($currentOwner !== 0 && !in_array($currentOwner, $removedUserIds, true)) {
            return;
        }

        // Rule 3: fill (owner was 0) or fall back (owner was just removed).
        $primary = !empty($assigneeIds) ? $assigneeIds[0] : 0;

        if ($primary !== $currentOwner) {
            $this->taskModificationModel->update(['id' => $taskId, 'owner_id' => $primary], false);
        }
    }

    /**
     * Search teams by name for a given project (used by the unified type-ahead picker).
     *
     * @param int    $projectId
     * @param string $query
     * @return array
     */
    public function searchTeams(int $projectId, string $query): array
    {
        $builder = $this->db
            ->table(self::TEAMS_TABLE)
            ->columns('id', 'name')
            ->beginOr()
            ->eq('project_id', $projectId)
            ->isNull('project_id')
            ->closeOr();

        if ($query !== '') {
            $builder->ilike('name', '%' . $query . '%');
        }

        return $builder->findAll();
    }

    /**
     * Update the role for a specific assignee on a task.
     *
     * Requires both assigneeId and taskId for security (prevents cross-task modification).
     *
     * @param int         $assigneeId
     * @param int         $taskId
     * @param string|null $role
     * @return bool
     */
    public function updateRole(int $assigneeId, int $taskId, ?string $role): bool
    {
        return (bool) $this->db->table(self::TABLE)
            ->eq('id', $assigneeId)
            ->eq('task_id', $taskId)
            ->update(['role' => $role]);
    }

    /**
     * Retroactive migration: apply Primary/Helper roles to existing tasks.
     *
     * For each task in the project, sets role='Primary' on position=1 and
     * role='Helper' on all others — but ONLY where role IS NULL.
     * This makes the operation idempotent and safe to re-run.
     *
     * @param int $projectId
     * @return void
     */
    public function applyPrimaryHelperRoles(int $projectId): void
    {
        $taskIds = $this->db->table(self::TABLE)
            ->columns(self::TABLE . '.task_id')
            ->join('tasks', 'id', 'task_id', self::TABLE)
            ->eq('tasks.project_id', $projectId)
            ->groupBy(self::TABLE . '.task_id')
            ->findAllByColumn('task_id');

        foreach ($taskIds as $taskId) {
            $assignees = $this->getAssigneesForTask((int) $taskId);
            foreach ($assignees as $a) {
                if ($a['role'] === null || $a['role'] === '') {
                    $newRole = ((int) $a['position'] === 1) ? 'Primary' : 'Helper';
                    $this->db->table(self::TABLE)
                        ->eq('id', $a['id'])
                        ->update(['role' => $newRole]);
                }
            }
        }
    }

    /**
     * Resequence positions after a removal to close gaps.
     *
     * @param int $taskId
     * @return void
     */
    private function resequencePositions(int $taskId): void
    {
        $rows = $this->db
            ->table(self::TABLE)
            ->eq('task_id', $taskId)
            ->asc('position')
            ->columns('id')
            ->findAll();

        $position = 1;
        foreach ($rows as $row) {
            $this->db
                ->table(self::TABLE)
                ->eq('id', $row['id'])
                ->update(['position' => $position]);
            $position++;
        }
    }
}
