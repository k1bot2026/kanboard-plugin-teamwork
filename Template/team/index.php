<div class="page-header">
    <h2><?= t('Team Management') ?></h2>
</div>

<form class="teamwork-create-team-form" data-create-url="<?= $this->url->href('TeamController', 'create', ['project_id' => $project['id'], 'plugin' => 'TeamWork']) ?>" data-csrf="<?= $this->text->e($csrf_token) ?>">
    <input type="text" name="name" placeholder="<?= t('New team name...') ?>" class="teamwork-team-name-input" required>
    <button type="submit" class="btn btn-blue"><?= t('Create Team') ?></button>
</form>

<?php if (empty($teams)): ?>
    <p class="alert"><?= t('No teams defined yet. Create a team above.') ?></p>
<?php else: ?>
<div class="teamwork-team-list"
     data-rename-url="<?= $this->url->href('TeamController', 'rename', ['project_id' => $project['id'], 'plugin' => 'TeamWork']) ?>"
     data-remove-url="<?= $this->url->href('TeamController', 'remove', ['project_id' => $project['id'], 'plugin' => 'TeamWork']) ?>"
     data-add-member-url="<?= $this->url->href('TeamController', 'addMember', ['project_id' => $project['id'], 'plugin' => 'TeamWork']) ?>"
     data-remove-member-url="<?= $this->url->href('TeamController', 'removeMember', ['project_id' => $project['id'], 'plugin' => 'TeamWork']) ?>"
     data-search-members-url="<?= $this->url->href('TeamController', 'searchMembers', ['project_id' => $project['id'], 'plugin' => 'TeamWork']) ?>"
     data-csrf="<?= $this->text->e($csrf_token) ?>"
     data-project-id="<?= (int)$project['id'] ?>">

    <?php foreach ($teams as $team): ?>
    <div class="teamwork-team-card" data-team-id="<?= (int)$team['id'] ?>">
        <div class="teamwork-team-header">
            <span class="teamwork-team-name"><?= $this->text->e($team['name']) ?></span>
            <span class="teamwork-team-count">(<?= $team['member_count'] ?> <?= t('members') ?>)</span>
            <?php if ($team['is_global']): ?>
                <span class="teamwork-team-badge teamwork-badge-global"><?= t('Global') ?></span>
            <?php endif ?>
            <?php if (!$team['is_global']): ?>
                <a href="#" class="teamwork-team-rename" title="<?= t('Rename') ?>"><i class="fa fa-pencil"></i></a>
                <a href="#" class="teamwork-team-delete" title="<?= t('Delete') ?>"><i class="fa fa-trash-o"></i></a>
            <?php endif ?>
            <a href="#" class="teamwork-team-toggle"><i class="fa fa-caret-down"></i></a>
        </div>
        <div class="teamwork-team-body" style="display:none;">
            <?php include __DIR__ . '/members.php'; ?>
        </div>
    </div>
    <?php endforeach ?>

</div>
<?php endif ?>
