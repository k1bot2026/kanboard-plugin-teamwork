<?php

namespace Kanboard\Plugin\TeamWork\Model;

use Kanboard\Core\Base;

/**
 * TeamModel
 *
 * CRUD for plugin-defined teams (teamwork_teams) and their members
 * (teamwork_team_members). Teams are project-scoped collections of users
 * that can be expanded onto tasks via TaskAssigneeModel::addTeam().
 */
class TeamModel extends Base
{
    const TABLE = 'teamwork_teams';
    const MEMBERS_TABLE = 'teamwork_team_members';

    /**
     * Create a new team.
     *
     * @param string   $name      Team name (must be non-empty)
     * @param int|null $projectId Optional project scope (NULL = global)
     * @return int|false Inserted row ID, or false on failure
     */
    public function createTeam(string $name, ?int $projectId = null)
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        $result = $this->db->table(self::TABLE)->insert([
            'name'       => $name,
            'project_id' => $projectId,
            'created_at' => time(),
        ]);

        if ($result === false) {
            return false;
        }

        return (int) $this->db->getLastId();
    }

    /**
     * Get a team by its ID.
     *
     * @param int $teamId
     * @return array|false Team row or false if not found
     */
    public function getTeamById(int $teamId)
    {
        return $this->db
            ->table(self::TABLE)
            ->eq('id', $teamId)
            ->findOne();
    }

    /**
     * Get all teams available for a project.
     *
     * Returns teams scoped to the given project plus global teams (project_id IS NULL).
     *
     * @param int $projectId
     * @return array
     */
    public function getTeamsForProject(int $projectId): array
    {
        return $this->db
            ->table(self::TABLE)
            ->beginOr()
            ->eq('project_id', $projectId)
            ->isNull('project_id')
            ->closeOr()
            ->asc('name')
            ->findAll();
    }

    /**
     * Search teams by name for a given project (case-insensitive).
     *
     * If query is empty, returns all teams for the project.
     *
     * @param int    $projectId
     * @param string $query
     * @return array Array of ['id', 'name'] rows
     */
    public function search(int $projectId, string $query): array
    {
        if ($query === '') {
            return $this->getTeamsForProject($projectId);
        }

        return $this->db
            ->table(self::TABLE)
            ->columns('id', 'name')
            ->beginOr()
            ->eq('project_id', $projectId)
            ->isNull('project_id')
            ->closeOr()
            ->ilike('name', '%' . $query . '%')
            ->findAll();
    }

    /**
     * Add a user to a team.
     *
     * Returns false if the user is already a member (PK violation).
     *
     * @param int $teamId
     * @param int $userId
     * @return bool
     */
    public function addMember(int $teamId, int $userId): bool
    {
        $result = $this->db->table(self::MEMBERS_TABLE)->insert([
            'team_id' => $teamId,
            'user_id' => $userId,
        ]);

        return $result !== false;
    }

    /**
     * Remove a user from a team.
     *
     * @param int $teamId
     * @param int $userId
     * @return bool
     */
    public function removeMember(int $teamId, int $userId): bool
    {
        return (bool) $this->db
            ->table(self::MEMBERS_TABLE)
            ->eq('team_id', $teamId)
            ->eq('user_id', $userId)
            ->remove();
    }

    /**
     * Rename a team.
     *
     * @param int    $teamId
     * @param string $name New name (trimmed; empty rejected)
     * @return bool
     */
    public function renameTeam(int $teamId, string $name): bool
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        return (bool) $this->db->table(self::TABLE)
            ->eq('id', $teamId)
            ->update(['name' => $name]);
    }

    /**
     * Delete a team.
     *
     * Team members are cascade-deleted by the FK constraint defined in Phase 1 schema.
     *
     * @param int $teamId
     * @return bool
     */
    public function deleteTeam(int $teamId): bool
    {
        return (bool) $this->db->table(self::TABLE)
            ->eq('id', $teamId)
            ->remove();
    }

    /**
     * Get all members of a team, joined with user info.
     *
     * @param int $teamId
     * @return array
     */
    public function getMembers(int $teamId): array
    {
        return $this->db
            ->table(self::MEMBERS_TABLE)
            ->columns(
                self::MEMBERS_TABLE . '.user_id',
                'users.username',
                'users.name',
                'users.email'
            )
            ->join('users', 'id', 'user_id', self::MEMBERS_TABLE)
            ->eq(self::MEMBERS_TABLE . '.team_id', $teamId)
            ->asc('users.name')
            ->findAll();
    }
}
