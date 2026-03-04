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
        $result = $this->db
            ->table(self::TABLE)
            ->eq('id', $assigneeId)
            ->eq('task_id', $taskId)
            ->remove();

        if (!$result) {
            return false;
        }

        $this->resequencePositions($taskId);
        $this->syncPrimaryAssignee($taskId);

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
     * Expand a plugin-defined team onto a task.
     *
     * Fetches team members from teamwork_team_members and adds each as an assignee
     * with source_type='team' and source_id=$teamId. Duplicates are silently skipped.
     *
     * @param int $taskId
     * @param int $teamId
     * @return int Count of newly added members
     */
    public function addTeam(int $taskId, int $teamId): int
    {
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
        $count = $this->db
            ->table(self::TABLE)
            ->eq('task_id', $taskId)
            ->eq('source_type', 'group')
            ->eq('source_id', $groupId)
            ->count();

        if ($count > 0) {
            $this->db
                ->table(self::TABLE)
                ->eq('task_id', $taskId)
                ->eq('source_type', 'group')
                ->eq('source_id', $groupId)
                ->remove();

            $this->resequencePositions($taskId);
            $this->syncPrimaryAssignee($taskId);
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
        $count = $this->db
            ->table(self::TABLE)
            ->eq('task_id', $taskId)
            ->eq('source_type', 'team')
            ->eq('source_id', $teamId)
            ->count();

        if ($count > 0) {
            $this->db
                ->table(self::TABLE)
                ->eq('task_id', $taskId)
                ->eq('source_type', 'team')
                ->eq('source_id', $teamId)
                ->remove();

            $this->resequencePositions($taskId);
            $this->syncPrimaryAssignee($taskId);
        }

        return $count;
    }

    /**
     * Sync the primary assignee to Kanboard's tasks.owner_id.
     *
     * Sets owner_id to the user_id of the first assignee by position,
     * or 0 if no assignees remain. The second argument (false) to update()
     * is CRITICAL — it disables event firing to prevent event loops.
     *
     * @param int $taskId
     * @return void
     */
    public function syncPrimaryAssignee(int $taskId): void
    {
        $result = $this->db
            ->table(self::TABLE)
            ->eq('task_id', $taskId)
            ->asc('position')
            ->findOneColumn('user_id');

        $primary = $result ? (int) $result : 0;

        $this->taskModificationModel->update(['id' => $taskId, 'owner_id' => $primary], false);
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
