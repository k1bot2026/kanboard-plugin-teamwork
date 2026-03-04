<?php
// Variables available: $task, $tw_assignment_mode, $tw_custom_roles, $tw_assignees, $tw_csrf_token
// Injected via template:task:details:third-column attachCallable hook
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

// Group assignees by their source for rendering
// Individual users: source_type = 'user'
// Group members: source_type = 'group', grouped by source_id
// Team members: source_type = 'team', grouped by source_id
$grouped = [];
foreach ($assignees as $a) {
    $key = $a['source_type'] . '_' . ($a['source_id'] ?? 0);
    $grouped[$key]['source_type'] = $a['source_type'];
    $grouped[$key]['source_id']   = $a['source_id'];
    $grouped[$key]['members'][]   = $a;
}
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

    <?php include __DIR__ . '/picker.php' ?>
</div>
