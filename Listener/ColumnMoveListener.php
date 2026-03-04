<?php

namespace Kanboard\Plugin\TeamWork\Listener;

use Kanboard\Core\Base;

/**
 * ColumnMoveListener
 *
 * Applies automation rules when a task enters a column.
 * For each rule on the target column, sets the configured role
 * on all existing assignees of the task.
 */
class ColumnMoveListener extends Base
{
    /**
     * Handle a column move event.
     *
     * @param array  $eventData Event data containing task info
     * @param string $eventName The event name
     * @return void
     */
    public function handle(array $eventData, string $eventName): void
    {
        $task = $eventData['task'] ?? $eventData;

        $taskId    = (int) ($task['id'] ?? 0);
        $projectId = (int) ($task['project_id'] ?? 0);
        $columnId  = (int) ($task['column_id'] ?? 0);

        if ($taskId === 0 || $projectId === 0 || $columnId === 0) {
            return;
        }

        $rules = $this->automationRuleModel->getRulesForColumn($projectId, $columnId);

        if (empty($rules)) {
            return;
        }

        $assignees = $this->taskAssigneeModel->getAssigneesForTask($taskId);

        if (empty($assignees)) {
            return;
        }

        foreach ($rules as $rule) {
            foreach ($assignees as $assignee) {
                $this->taskAssigneeModel->updateRole(
                    (int) $assignee['id'],
                    $taskId,
                    $rule['role']
                );
            }
        }
    }
}
