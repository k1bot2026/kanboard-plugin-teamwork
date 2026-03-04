<?php

namespace Kanboard\Plugin\TeamWork;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Model\TaskModel;
use Kanboard\Model\CommentModel;
use Kanboard\Model\SubtaskModel;
use Kanboard\Plugin\TeamWork\Filter\TaskTeamworkAssigneeFilter;
use Kanboard\Plugin\TeamWork\Filter\TaskTeamworkRoleFilter;
use Kanboard\Event\GenericEvent;

class Plugin extends Base
{
    public function initialize(): void
    {
        $container = $this->container;

        // Register BoardAvatarHelper in the template helper subsystem
        // (getClasses registers it in the main container, but templates
        // access helpers via $this->helper->name which uses a separate registry)
        $this->helper->register('boardAvatarHelper', '\Kanboard\Plugin\TeamWork\Helper\BoardAvatarHelper');

        // Routes
        $this->route->addRoute('/teamwork/search/assignees', 'AssigneeController', 'search', 'teamwork');
        $this->route->addRoute('/teamwork/task/:task_id/assignees/add', 'AssigneeController', 'add', 'teamwork');
        $this->route->addRoute('/teamwork/task/:task_id/assignees/remove/:assignee_id', 'AssigneeController', 'remove', 'teamwork');
        $this->route->addRoute('/teamwork/task/:task_id/assignees/remove-group/:group_id', 'AssigneeController', 'removeGroup', 'teamwork');
        $this->route->addRoute('/teamwork/task/:task_id/assignees/remove-team/:team_id', 'AssigneeController', 'removeTeam', 'teamwork');
        $this->route->addRoute('/teamwork/task/:task_id/assignees/update-role', 'AssigneeController', 'updateRole', 'teamwork');

        // Settings routes
        $this->route->addRoute('/teamwork/project/:project_id/settings/assignment-mode', 'SettingsController', 'assignmentMode', 'teamwork');
        $this->route->addRoute('/teamwork/project/:project_id/settings/save-assignment-mode', 'SettingsController', 'saveAssignmentMode', 'teamwork');

        // Team management routes
        $this->route->addRoute('/teamwork/project/:project_id/teams', 'TeamController', 'index', 'teamwork');
        $this->route->addRoute('/teamwork/project/:project_id/teams/create', 'TeamController', 'create', 'teamwork');
        $this->route->addRoute('/teamwork/project/:project_id/teams/rename', 'TeamController', 'rename', 'teamwork');
        $this->route->addRoute('/teamwork/project/:project_id/teams/remove', 'TeamController', 'remove', 'teamwork');
        $this->route->addRoute('/teamwork/project/:project_id/teams/add-member', 'TeamController', 'addMember', 'teamwork');
        $this->route->addRoute('/teamwork/project/:project_id/teams/remove-member', 'TeamController', 'removeMember', 'teamwork');
        $this->route->addRoute('/teamwork/project/:project_id/teams/search-members', 'TeamController', 'searchMembers', 'teamwork');

        // Automation rules routes
        $this->route->addRoute('/teamwork/project/:project_id/automation', 'AutomationController', 'index', 'teamwork');
        $this->route->addRoute('/teamwork/project/:project_id/automation/add-rule', 'AutomationController', 'addRule', 'teamwork');
        $this->route->addRoute('/teamwork/project/:project_id/automation/remove-rule', 'AutomationController', 'removeRule', 'teamwork');

        // ACL — project members can access all assignee controller actions
        $this->projectAccessMap->add('AssigneeController', '*', Role::PROJECT_MEMBER);

        // ACL — only project managers/admins can configure settings
        $this->projectAccessMap->add('SettingsController', '*', Role::PROJECT_MANAGER);

        // ACL — team management: read access for members, write access for managers
        $this->projectAccessMap->add('TeamController', ['index', 'searchMembers'], Role::PROJECT_MEMBER);
        $this->projectAccessMap->add('TeamController', ['create', 'rename', 'remove', 'addMember', 'removeMember'], Role::PROJECT_MANAGER);

        // ACL — only project managers/admins can configure automation rules
        $this->projectAccessMap->add('AutomationController', '*', Role::PROJECT_MANAGER);

        // Template hook — injects + button and assignee list into task detail (with assignment mode)
        $this->template->hook->attachCallable(
            'template:task:details:third-column',
            'TeamWork:assignee/show',
            function (array $task) use ($container) {
                $mode = $container['projectMetadataModel']->get(
                    $task['project_id'], 'teamwork_assignment_mode', 'equal'
                );
                $customRoles = $container['projectMetadataModel']->get(
                    $task['project_id'], 'teamwork_custom_roles', ''
                );
                $assignees = $container['taskAssigneeModel']->getAssigneesForTask($task['id']);
                $csrfToken = $container['token']->getReusableCSRFToken();
                return [
                    'tw_assignment_mode' => $mode,
                    'tw_custom_roles'    => $customRoles,
                    'tw_assignees'       => $assignees,
                    'tw_csrf_token'      => $csrfToken,
                ];
            }
        );

        // Template hook — injects assignee widget into the task edit modal (card popup)
        $this->template->hook->attachCallable(
            'template:task:form:second-column',
            'TeamWork:assignee/form_widget',
            function (array $values) use ($container) {
                // Only show when editing existing tasks (not during creation)
                if (empty($values['id'])) {
                    return ['tw_show_widget' => false];
                }
                $taskId = (int) $values['id'];
                $projectId = (int) $values['project_id'];
                $assignees = $container['taskAssigneeModel']->getAssigneesForTask($taskId);
                $csrfToken = $container['token']->getReusableCSRFToken();
                $mode = $container['projectMetadataModel']->get(
                    $projectId, 'teamwork_assignment_mode', 'equal'
                );
                $customRoles = $container['projectMetadataModel']->get(
                    $projectId, 'teamwork_custom_roles', ''
                );
                return [
                    'tw_show_widget'     => true,
                    'tw_task_id'         => $taskId,
                    'tw_project_id'      => $projectId,
                    'tw_assignees'       => $assignees,
                    'tw_csrf_token'      => $csrfToken,
                    'tw_assignment_mode' => $mode,
                    'tw_custom_roles'    => $customRoles,
                ];
            }
        );

        // Sidebar hook — adds Assignment Mode and Team Management links to project settings
        $this->template->hook->attach('template:project:sidebar', 'TeamWork:settings/sidebar');

        // Assets — loaded on every page
        $this->hook->on('template:layout:js',  ['template' => 'plugins/TeamWork/Asset/teamwork.js']);
        $this->hook->on('template:layout:css', ['template' => 'plugins/TeamWork/Asset/teamwork.css']);

        // Board card avatar stacks (private board)
        $this->template->hook->attachCallable(
            'template:board:private:task:after-title',
            'TeamWork:board/avatar_stack',
            function (array $task) use ($container) {
                return [
                    'tw_assignees' => $container['boardAvatarHelper']
                        ->getAssigneesForTask($task['id'], $task['project_id']),
                ];
            }
        );

        // Board card avatar stacks (public board)
        $this->template->hook->attachCallable(
            'template:board:public:task:after-title',
            'TeamWork:board/avatar_stack',
            function (array $task) use ($container) {
                return [
                    'tw_assignees' => $container['boardAvatarHelper']
                        ->getAssigneesForTask($task['id'], $task['project_id']),
                ];
            }
        );

        // --- Notification fan-out event listeners ---
        // Priority -10: fire AFTER Kanboard's built-in NotificationSubscriber

        // Task events (standard handler)
        $standardEvents = [
            TaskModel::EVENT_UPDATE,
            TaskModel::EVENT_CLOSE,
            TaskModel::EVENT_MOVE_COLUMN,
            TaskModel::EVENT_MOVE_POSITION,
            TaskModel::EVENT_MOVE_SWIMLANE,
            TaskModel::EVENT_ASSIGNEE_CHANGE,
            TaskModel::EVENT_OVERDUE,
        ];

        foreach ($standardEvents as $eventName) {
            $this->dispatcher->addListener($eventName, function (GenericEvent $event, $eventName) use ($container) {
                $container['notificationDispatcher']->handle($event->getAll(), $eventName);
            }, -10);
        }

        // Comment events (standard handler)
        $commentEvents = [
            CommentModel::EVENT_CREATE,
            CommentModel::EVENT_USER_MENTION,
        ];

        foreach ($commentEvents as $eventName) {
            $this->dispatcher->addListener($eventName, function (GenericEvent $event, $eventName) use ($container) {
                $container['notificationDispatcher']->handle($event->getAll(), $eventName);
            }, -10);
        }

        // Subtask events (standard handler)
        $subtaskEvents = [
            SubtaskModel::EVENT_CREATE,
            SubtaskModel::EVENT_UPDATE,
            SubtaskModel::EVENT_DELETE,
        ];

        foreach ($subtaskEvents as $eventName) {
            $this->dispatcher->addListener($eventName, function (GenericEvent $event, $eventName) use ($container) {
                $container['notificationDispatcher']->handle($event->getAll(), $eventName);
            }, -10);
        }

        // --- Column move automation listener ---
        // Fires automation rules when a task moves to a column
        $this->dispatcher->addListener(TaskModel::EVENT_MOVE_COLUMN, function (GenericEvent $event, $eventName) use ($container) {
            $container['columnMoveListener']->handle($event->getAll(), $eventName);
        }, -20);

        // Also fire automation rules on task.update if column changed (API moves)
        $this->dispatcher->addListener(TaskModel::EVENT_UPDATE, function (GenericEvent $event, $eventName) use ($container) {
            $eventData = $event->getAll();
            if (isset($eventData['changes']['column_id'])) {
                $container['columnMoveListener']->handle($eventData, $eventName);
            }
        }, -20);

        // Custom TeamWork assignee events (assignee change handler)
        $this->dispatcher->addListener('teamwork.assignee.add', function (GenericEvent $event, $eventName) use ($container) {
            $container['notificationDispatcher']->handleAssigneeChange($event->getAll(), $eventName);
        }, -10);

        $this->dispatcher->addListener('teamwork.assignee.remove', function (GenericEvent $event, $eventName) use ($container) {
            $container['notificationDispatcher']->handleAssigneeChange($event->getAll(), $eventName);
        }, -10);

        // --- Search filter extensions ---
        // Override built-in assignee: filter with TeamWork-aware superset and add new role: filter
        $this->container->extend('taskLexer', function ($taskLexer, $c) {
            $taskLexer
                ->withFilter(
                    TaskTeamworkAssigneeFilter::getInstance()
                        ->setDatabase($c['db'])
                        ->setCurrentUserId($c['userSession']->getId())
                )
                ->withFilter(
                    TaskTeamworkRoleFilter::getInstance()
                        ->setDatabase($c['db'])
                );
            return $taskLexer;
        });

        // --- My Tasks dashboard extension ---
        // Include tasks where current user is a TeamWork assignee in any role
        $this->hook->on('pagination:dashboard:task:query', function (&$query) {
            $userId = (int) $this->userSession->getId();
            $query->addCondition(
                'tasks.id IN (SELECT task_id FROM teamwork_task_assignees WHERE user_id = ' . $userId . ') OR tasks.owner_id = ' . $userId
            );
        });
    }

    public function getClasses(): array
    {
        return [
            'Plugin\TeamWork\Model'      => ['TaskAssigneeModel', 'TeamModel', 'AutomationRuleModel'],
            'Plugin\TeamWork\Helper'     => ['BoardAvatarHelper'],
            'Plugin\TeamWork\Subscriber' => ['NotificationDispatcher'],
            'Plugin\TeamWork\Listener'   => ['ColumnMoveListener'],
        ];
    }

    public function getPluginName(): string
    {
        return 'TeamWork';
    }

    public function getPluginDescription(): string
    {
        return 'Multi-person task assignment for Kanboard';
    }

    public function getPluginAuthor(): string
    {
        return 'k1bot2026';
    }

    public function getPluginVersion(): string
    {
        return '1.0.0';
    }

    public function getCompatibleVersion(): string
    {
        return '>=1.2.46';
    }

    public function getPluginHomepage(): string
    {
        return 'https://github.com/k1bot2026/kanboard-plugin-teamwork';
    }
}
