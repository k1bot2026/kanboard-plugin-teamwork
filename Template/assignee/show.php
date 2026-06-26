<?php
// Variables available: $task, $tw_assignment_mode, $tw_custom_roles, $tw_assignees, $tw_csrf_token
// Injected via template:task:details:third-column attachCallable hook
// When TeamWork is disabled for this project, only tw_assignees (empty) is passed — exit early.
if (empty($tw_csrf_token)) return;
$assignees      = $tw_assignees;
$csrfToken      = $tw_csrf_token;
$searchUrl      = $this->helper->url->to('AssigneeController', 'search',
                      ['project_id' => $task['project_id'], 'plugin' => 'TeamWork']);
$addUrl         = $this->helper->url->to('AssigneeController', 'add',
                      ['task_id' => $task['id'], 'plugin' => 'TeamWork']);
$removeUrl      = $this->helper->url->to('AssigneeController', 'remove',
                      ['task_id' => $task['id'], 'assignee_id' => '__AID__', 'plugin' => 'TeamWork']);
$removeGroupUrl = $this->helper->url->to('AssigneeController', 'removeGroup',
                      ['task_id' => $task['id'], 'group_id' => '__GID__', 'plugin' => 'TeamWork']);
$removeTeamUrl  = $this->helper->url->to('AssigneeController', 'removeTeam',
                      ['task_id' => $task['id'], 'team_id' => '__TID__', 'plugin' => 'TeamWork']);
$updateRoleUrl  = $this->helper->url->to('AssigneeController', 'updateRole',
                      ['task_id' => $task['id'], 'plugin' => 'TeamWork']);

// Default assignment mode if not set (backward compatibility)
$tw_assignment_mode = isset($tw_assignment_mode) ? $tw_assignment_mode : 'equal';
$tw_custom_roles    = isset($tw_custom_roles) ? $tw_custom_roles : '';

// Structured assignment view (individuals / teams / groups) built server-side.
$view = isset($tw_view) ? $tw_view : ['individuals' => [], 'teams' => [], 'groups' => []];
?>

<!-- teamwork-extension wraps the entire plugin addition; CSS positions it to flow below the native owner row -->
<div class="teamwork-extension"
     data-add-url="<?= $this->text->e($addUrl) ?>"
     data-remove-url="<?= $this->text->e($removeUrl) ?>"
     data-remove-group-url="<?= $this->text->e($removeGroupUrl) ?>"
     data-remove-team-url="<?= $this->text->e($removeTeamUrl) ?>"
     data-update-role-url="<?= $this->text->e($updateRoleUrl) ?>"
     data-assignment-mode="<?= $this->text->e($tw_assignment_mode) ?>"
     data-custom-roles="<?= $this->text->e($tw_custom_roles) ?>"
     data-csrf="<?= $this->text->e($csrfToken) ?>">

    <!-- + button: appears adjacent to native owner field, opens picker inline -->
    <button type="button" class="teamwork-add-btn" title="<?= t('Add assignee, group, or team...') ?>">
        <i class="fa fa-plus"></i>
    </button>

    <!-- Grouped assignee list (hidden when empty, shown after first add) -->
    <?php include __DIR__ . '/list.php' ?>

    <?php include __DIR__ . '/picker.php' ?>
</div>
