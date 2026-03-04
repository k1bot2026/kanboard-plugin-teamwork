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

// Group assignees by their source for rendering
$grouped = [];
foreach ($assignees as $a) {
    $key = $a['source_type'] . '_' . ($a['source_id'] ?? 0);
    $grouped[$key]['source_type'] = $a['source_type'];
    $grouped[$key]['source_id']   = $a['source_id'];
    $grouped[$key]['members'][]   = $a;
}
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
        <?php if (!empty($grouped)): ?>
        <ul class="teamwork-assignee-list">
            <?php foreach ($grouped as $group): ?>
                <?php if ($group['source_type'] === 'user'): ?>
                    <?php foreach ($group['members'] as $a): ?>
                    <li class="teamwork-assignee-item" data-assignee-id="<?= (int)$a['id'] ?>">
                        <i class="fa fa-user teamwork-type-icon"></i>
                        <span class="teamwork-assignee-name">
                            <?= $this->text->e($a['name'] ?: $a['username']) ?>
                            <?php if ($tw_assignment_mode !== 'equal'): ?>
                                <?php if (!empty($a['role'])): ?>
                                    <span class="teamwork-role-label teamwork-role-clickable" data-assignee-id="<?= (int)$a['id'] ?>"><?= $this->text->e($a['role']) ?></span>
                                <?php else: ?>
                                    <a href="#" class="teamwork-set-role" data-assignee-id="<?= (int)$a['id'] ?>"><?= t('Set role') ?></a>
                                <?php endif ?>
                            <?php endif ?>
                        </span>
                        <a href="#" class="teamwork-remove-individual"
                           data-assignee-id="<?= (int)$a['id'] ?>"
                           title="<?= t('Remove') ?>"><i class="fa fa-times"></i></a>
                    </li>
                    <?php endforeach ?>
                <?php elseif ($group['source_type'] === 'group'): ?>
                    <?php $first = $group['members'][0]; $count = count($group['members']); ?>
                    <li class="teamwork-group-row" data-source-type="group" data-source-id="<?= (int)$group['source_id'] ?>">
                        <a href="#" class="teamwork-group-toggle">
                            <i class="fa fa-users teamwork-type-icon"></i>
                            <span class="teamwork-group-label">
                                <?= $this->text->e($first['name'] ?: $first['username']) ?><?php if ($count > 1): ?>&nbsp;(<?= $count ?>)<?php endif ?>
                            </span>
                            <i class="fa fa-caret-down teamwork-caret"></i>
                        </a>
                        <a href="#" class="teamwork-remove-source"
                           data-source-type="group"
                           data-source-id="<?= (int)$group['source_id'] ?>"
                           title="<?= t('Remove all') ?>"><i class="fa fa-times"></i></a>
                        <ul class="teamwork-group-members" style="display:none;">
                            <?php foreach ($group['members'] as $a): ?>
                            <li><?= $this->text->e($a['name'] ?: $a['username']) ?></li>
                            <?php endforeach ?>
                        </ul>
                    </li>
                <?php elseif ($group['source_type'] === 'team'): ?>
                    <?php $first = $group['members'][0]; $count = count($group['members']); ?>
                    <li class="teamwork-group-row" data-source-type="team" data-source-id="<?= (int)$group['source_id'] ?>">
                        <a href="#" class="teamwork-group-toggle">
                            <i class="fa fa-sitemap teamwork-type-icon"></i>
                            <span class="teamwork-group-label">
                                <?= $this->text->e($first['name'] ?: $first['username']) ?><?php if ($count > 1): ?>&nbsp;(<?= $count ?>)<?php endif ?>
                            </span>
                            <i class="fa fa-caret-down teamwork-caret"></i>
                        </a>
                        <a href="#" class="teamwork-remove-source"
                           data-source-type="team"
                           data-source-id="<?= (int)$group['source_id'] ?>"
                           title="<?= t('Remove all') ?>"><i class="fa fa-times"></i></a>
                        <ul class="teamwork-group-members" style="display:none;">
                            <?php foreach ($group['members'] as $a): ?>
                            <li><?= $this->text->e($a['name'] ?: $a['username']) ?></li>
                            <?php endforeach ?>
                        </ul>
                    </li>
                <?php endif ?>
            <?php endforeach ?>
        </ul>
        <?php endif ?>

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
