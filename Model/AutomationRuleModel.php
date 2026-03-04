<?php

namespace Kanboard\Plugin\TeamWork\Model;

use Kanboard\Core\Base;

/**
 * AutomationRuleModel
 *
 * CRUD for column-transition automation rules.
 * When a task enters a column with a rule, all existing assignees get the configured role.
 */
class AutomationRuleModel extends Base
{
    const TABLE = 'teamwork_automation_rules';

    /**
     * Get all automation rules for a project, with column name.
     *
     * @param int $projectId
     * @return array
     */
    public function getRulesForProject(int $projectId): array
    {
        return $this->db
            ->table(self::TABLE)
            ->columns(self::TABLE . '.*', 'columns.title AS column_name')
            ->join('columns', 'id', 'column_id', self::TABLE)
            ->eq(self::TABLE . '.project_id', $projectId)
            ->findAll();
    }

    /**
     * Get automation rules for a specific column in a project.
     *
     * @param int $projectId
     * @param int $columnId
     * @return array
     */
    public function getRulesForColumn(int $projectId, int $columnId): array
    {
        return $this->db
            ->table(self::TABLE)
            ->eq('project_id', $projectId)
            ->eq('column_id', $columnId)
            ->findAll();
    }

    /**
     * Create a new automation rule.
     *
     * @param int    $projectId
     * @param int    $columnId
     * @param string $role
     * @return bool
     */
    public function createRule(int $projectId, int $columnId, string $role): bool
    {
        return (bool) $this->db->table(self::TABLE)->insert([
            'project_id' => $projectId,
            'column_id'  => $columnId,
            'role'       => $role,
            'created_at' => time(),
        ]);
    }

    /**
     * Remove an automation rule.
     *
     * Requires both ruleId AND projectId for security (prevents cross-project deletion).
     *
     * @param int $ruleId
     * @param int $projectId
     * @return bool
     */
    public function removeRule(int $ruleId, int $projectId): bool
    {
        return (bool) $this->db
            ->table(self::TABLE)
            ->eq('id', $ruleId)
            ->eq('project_id', $projectId)
            ->remove();
    }
}
