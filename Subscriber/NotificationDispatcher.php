<?php

namespace Kanboard\Plugin\TeamWork\Subscriber;

use Kanboard\Core\Base;
use Kanboard\Model\TaskModel;
use Kanboard\Model\CommentModel;
use Kanboard\Model\SubtaskModel;

/**
 * NotificationDispatcher
 *
 * Fan-out notification dispatcher for TeamWork multi-assignee plugin.
 *
 * Listens to Kanboard task/comment/subtask events and dispatches notifications
 * to all TeamWork assignees based on their role:
 *   - ALL assignees receive: comments, user mentions, task close, overdue, due-date changes
 *   - PRIMARY-only receive: column moves, priority/title/description changes, subtask events
 *
 * Skips self-notification (the user who triggered the event) and the native
 * owner_id (already notified by Kanboard's built-in NotificationSubscriber).
 */
class NotificationDispatcher extends Base
{
    /**
     * Events that ALL assignees (primary + helper) receive.
     */
    const EVENTS_ALL_ROLES = [
        CommentModel::EVENT_CREATE,
        CommentModel::EVENT_USER_MENTION,
        TaskModel::EVENT_CLOSE,
        TaskModel::EVENT_OVERDUE,
    ];

    /**
     * Events that ONLY primary assignees receive.
     * Helpers are filtered out for these events (unless shouldFilterHelpers overrides).
     */
    const EVENTS_PRIMARY_ONLY = [
        TaskModel::EVENT_UPDATE,
        TaskModel::EVENT_MOVE_COLUMN,
        TaskModel::EVENT_MOVE_POSITION,
        TaskModel::EVENT_MOVE_SWIMLANE,
        TaskModel::EVENT_ASSIGNEE_CHANGE,
        SubtaskModel::EVENT_CREATE,
        SubtaskModel::EVENT_UPDATE,
        SubtaskModel::EVENT_DELETE,
    ];

    /**
     * Handle a standard task/comment/subtask event.
     *
     * Dispatches notifications to qualifying TeamWork assignees.
     * For task.overdue, iterates the plural $eventData['tasks'] array.
     *
     * @param array  $eventData Event payload from Symfony GenericEvent
     * @param string $eventName The event name constant
     */
    public function handle(array $eventData, string $eventName): void
    {
        // task.overdue sends an array of tasks, not a single task
        if ($eventName === TaskModel::EVENT_OVERDUE) {
            if (isset($eventData['tasks']) && is_array($eventData['tasks'])) {
                foreach ($eventData['tasks'] as $task) {
                    $singleEventData = $eventData;
                    $singleEventData['task'] = $task;
                    unset($singleEventData['tasks']);
                    $this->dispatchToAssignees($singleEventData, $eventName);
                }
            }
            return;
        }

        $this->dispatchToAssignees($eventData, $eventName);
    }

    /**
     * Handle custom teamwork.assignee.add and teamwork.assignee.remove events.
     *
     * Notifies only the specific user who was added/removed (not all assignees).
     * Skips self-notification (don't notify someone who assigned themselves).
     *
     * @param array  $eventData Event payload with 'task' and 'user_id' keys
     * @param string $eventName 'teamwork.assignee.add' or 'teamwork.assignee.remove'
     */
    public function handleAssigneeChange(array $eventData, string $eventName): void
    {
        $affectedUserId = isset($eventData['user_id']) ? (int) $eventData['user_id'] : 0;

        if ($affectedUserId === 0) {
            return;
        }

        // Don't notify the user who triggered the action (e.g., assigned themselves)
        $currentUserId = $this->userSession->getId();
        if ($affectedUserId === (int) $currentUserId) {
            return;
        }

        // Build a user array for the notification system
        $user = $this->db->table('users')
            ->columns('id', 'username', 'name', 'email')
            ->eq('id', $affectedUserId)
            ->findOne();

        if (empty($user)) {
            return;
        }

        if ($this->userNotificationFilterModel->shouldReceiveNotification($user, $eventData)) {
            $this->userNotificationModel->sendUserNotification($user, $eventName, $eventData);
        }
    }

    /**
     * Dispatch notifications to qualifying assignees for a single task event.
     *
     * @param array  $eventData
     * @param string $eventName
     */
    private function dispatchToAssignees(array $eventData, string $eventName): void
    {
        $taskId = $this->getTaskId($eventData);
        if ($taskId === null) {
            return;
        }

        $currentUserId = (int) $this->userSession->getId();
        $filterHelpers = $this->shouldFilterHelpers($eventName, $eventData);

        // Get the native owner_id to skip (Kanboard already notifies them)
        $ownerId = 0;
        if (isset($eventData['task']['owner_id'])) {
            $ownerId = (int) $eventData['task']['owner_id'];
        } else {
            $task = $this->taskFinderModel->getById($taskId);
            if (!empty($task)) {
                $ownerId = (int) $task['owner_id'];
            }
        }

        $assignees = $this->taskAssigneeModel->getAssigneesForTask($taskId);

        foreach ($assignees as $assignee) {
            $userId = (int) $assignee['user_id'];

            // Skip self-notification
            if ($userId === $currentUserId) {
                continue;
            }

            // Skip native owner_id to prevent duplicate notifications
            if ($userId === $ownerId) {
                continue;
            }

            // Filter helpers for primary-only events
            if ($filterHelpers && !$this->isPrimaryRole($assignee['role'])) {
                continue;
            }

            // Build user array for notification system
            $user = [
                'id'       => $assignee['user_id'],
                'username' => $assignee['username'],
                'name'     => $assignee['name'],
                'email'    => $assignee['email'],
            ];

            if ($this->userNotificationFilterModel->shouldReceiveNotification($user, $eventData)) {
                $this->userNotificationModel->sendUserNotification($user, $eventName, $eventData);
            }
        }
    }

    /**
     * Determine whether helpers should be filtered out for this event.
     *
     * Returns false for EVENTS_ALL_ROLES (everyone gets them).
     * Returns true for EVENTS_PRIMARY_ONLY (helpers filtered out).
     *
     * Special case: task.update with date_due change returns false because
     * due-date changes are high-signal events that ALL assignees must receive.
     *
     * @param string $eventName
     * @param array  $eventData
     * @return bool True if helpers should be excluded
     */
    public function shouldFilterHelpers(string $eventName, array $eventData): bool
    {
        if (!in_array($eventName, self::EVENTS_PRIMARY_ONLY, true)) {
            return false;
        }

        // Special case: task.update with due date change is high-signal for all roles
        if ($eventName === TaskModel::EVENT_UPDATE && isset($eventData['changes']['date_due'])) {
            return false;
        }

        return true;
    }

    /**
     * Check if a role is considered "primary" for notification filtering.
     *
     * 'Primary' or null (equal assignees mode) are treated as primary.
     *
     * @param string|null $role
     * @return bool
     */
    public function isPrimaryRole(?string $role): bool
    {
        return $role === 'Primary' || $role === null;
    }

    /**
     * Extract task_id from event data.
     *
     * Handles both $eventData['task']['id'] and $eventData['task_id'] patterns.
     *
     * @param array $eventData
     * @return int|null
     */
    public function getTaskId(array $eventData): ?int
    {
        if (isset($eventData['task']['id'])) {
            return (int) $eventData['task']['id'];
        }

        if (isset($eventData['task_id'])) {
            return (int) $eventData['task_id'];
        }

        return null;
    }
}
