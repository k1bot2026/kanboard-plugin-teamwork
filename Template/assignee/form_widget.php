<?php
// Variables available: $tw_show_widget, $tw_task_id, $tw_project_id,
// $tw_assignees, $tw_csrf_token, $tw_assignment_mode, $tw_custom_roles
// Injected via template:task:form:second-column attachCallable hook

// Don't render during task creation (no task ID yet)
if (empty($tw_show_widget)) {
    return;
}

$assignees      = $tw_assignees;
$csrfToken      = $tw_csrf_token;
$searchUrl      = $this->helper->url->to('AssigneeController', 'search',
                      ['project_id' => $tw_project_id, 'plugin' => 'TeamWork']);
$addUrl         = $this->helper->url->to('AssigneeController', 'add',
                      ['task_id' => $tw_task_id, 'plugin' => 'TeamWork']);
$removeUrl      = $this->helper->url->to('AssigneeController', 'remove',
                      ['task_id' => $tw_task_id, 'assignee_id' => '__AID__', 'plugin' => 'TeamWork']);
$removeGroupUrl = $this->helper->url->to('AssigneeController', 'removeGroup',
                      ['task_id' => $tw_task_id, 'group_id' => '__GID__', 'plugin' => 'TeamWork']);
$removeTeamUrl  = $this->helper->url->to('AssigneeController', 'removeTeam',
                      ['task_id' => $tw_task_id, 'team_id' => '__TID__', 'plugin' => 'TeamWork']);
$updateRoleUrl  = $this->helper->url->to('AssigneeController', 'updateRole',
                      ['task_id' => $tw_task_id, 'plugin' => 'TeamWork']);

$tw_assignment_mode = isset($tw_assignment_mode) ? $tw_assignment_mode : 'equal';
$tw_custom_roles    = isset($tw_custom_roles) ? $tw_custom_roles : '';

// Structured assignment view (individuals / teams / groups) built server-side.
$view = isset($tw_view) ? $tw_view : ['individuals' => [], 'teams' => [], 'groups' => []];
?>

<!-- TeamWork multi-assignee widget inside the task edit modal -->
<div class="task-form-bottom">
    <label><?= t('Team Assignees') ?></label>
    <div class="teamwork-extension"
         data-add-url="<?= $this->text->e($addUrl) ?>"
         data-remove-url="<?= $this->text->e($removeUrl) ?>"
         data-remove-group-url="<?= $this->text->e($removeGroupUrl) ?>"
         data-remove-team-url="<?= $this->text->e($removeTeamUrl) ?>"
         data-update-role-url="<?= $this->text->e($updateRoleUrl) ?>"
         data-assignment-mode="<?= $this->text->e($tw_assignment_mode) ?>"
         data-custom-roles="<?= $this->text->e($tw_custom_roles) ?>"
         data-csrf="<?= $this->text->e($csrfToken) ?>">

        <!-- + button: opens picker inline -->
        <button type="button" class="teamwork-add-btn" title="<?= t('Add assignee, group, or team...') ?>">
            <i class="fa fa-plus"></i>
        </button>

        <!-- Grouped assignee list -->
        <?php include __DIR__ . '/list.php' ?>

        <?php // Inline picker (same as show.php) ?>
        <div class="teamwork-picker" style="display:none;"
             data-search-url="<?= $this->text->e($searchUrl) ?>"
             data-add-url="<?= $this->text->e($addUrl) ?>"
             data-csrf="<?= $this->text->e($csrfToken) ?>">
            <input type="text"
                   class="teamwork-picker-input"
                   placeholder="<?= t('Add assignee, group, or team...') ?>"
                   autocomplete="off">
            <ul class="teamwork-picker-results"></ul>
        </div>
    </div>
</div>
