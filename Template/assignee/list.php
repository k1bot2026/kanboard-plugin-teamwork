<?php
// Shared renderer for the grouped assignee list (used by show.php and form_widget.php).
// Expects in scope:
//   $view               — ['individuals' => [...], 'teams' => [...], 'groups' => [...]]
//   $tw_assignment_mode  — 'equal' | 'primary_helpers' | 'custom'
$view = isset($view) ? $view : ['individuals' => [], 'teams' => [], 'groups' => []];
$tw_assignment_mode = isset($tw_assignment_mode) ? $tw_assignment_mode : 'equal';

$twHasAny = !empty($view['individuals']) || !empty($view['teams']) || !empty($view['groups']);
if ($twHasAny):
?>
<ul class="teamwork-assignee-list">
    <?php foreach ($view['individuals'] as $a): ?>
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

    <?php foreach ($view['groups'] as $group): ?>
    <li class="teamwork-group-row" data-source-type="group" data-source-id="<?= (int)$group['id'] ?>">
        <a href="#" class="teamwork-group-toggle">
            <i class="fa fa-users teamwork-type-icon"></i>
            <span class="teamwork-group-label">
                <?= $this->text->e($group['name']) ?>&nbsp;(<?= count($group['members']) ?>)
            </span>
            <i class="fa fa-caret-down teamwork-caret"></i>
        </a>
        <a href="#" class="teamwork-remove-source"
           data-source-type="group"
           data-source-id="<?= (int)$group['id'] ?>"
           title="<?= t('Remove all') ?>"><i class="fa fa-times"></i></a>
        <ul class="teamwork-group-members" style="display:none;">
            <?php foreach ($group['members'] as $a): ?>
            <li><?= $this->text->e($a['name'] ?: $a['username']) ?></li>
            <?php endforeach ?>
        </ul>
    </li>
    <?php endforeach ?>

    <?php foreach ($view['teams'] as $team): ?>
    <li class="teamwork-group-row" data-source-type="team" data-source-id="<?= (int)$team['id'] ?>">
        <a href="#" class="teamwork-group-toggle">
            <i class="fa fa-sitemap teamwork-type-icon"></i>
            <span class="teamwork-group-label">
                <?= $this->text->e($team['name']) ?>&nbsp;(<?= count($team['members']) ?>)
            </span>
            <i class="fa fa-caret-down teamwork-caret"></i>
        </a>
        <a href="#" class="teamwork-remove-source"
           data-source-type="team"
           data-source-id="<?= (int)$team['id'] ?>"
           title="<?= t('Remove all') ?>"><i class="fa fa-times"></i></a>
        <ul class="teamwork-group-members" style="display:none;">
            <?php if (empty($team['members'])): ?>
                <li class="teamwork-empty-team"><em><?= t('No members yet') ?></em></li>
            <?php else: ?>
                <?php foreach ($team['members'] as $a): ?>
                <li><?= $this->text->e($a['name'] ?: $a['username']) ?></li>
                <?php endforeach ?>
            <?php endif ?>
        </ul>
    </li>
    <?php endforeach ?>
</ul>
<?php endif ?>
