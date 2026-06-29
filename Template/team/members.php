<ul class="teamwork-member-list">
    <?php foreach ($team['members'] as $member): ?>
    <li class="teamwork-member-item" data-user-id="<?= (int)$member['user_id'] ?>">
        <span><?= $this->text->e($member['name'] ?: $member['username']) ?></span>
        <?php if (!$team['is_global']): ?>
        <a href="#" class="teamwork-member-remove" title="<?= t('Remove') ?>"><i class="fa fa-times"></i></a>
        <?php endif ?>
    </li>
    <?php endforeach ?>
    <?php if (empty($team['members'])): ?>
    <li class="teamwork-member-empty"><em><?= t('No members yet.') ?></em></li>
    <?php endif ?>
</ul>
<?php if (!$team['is_global']): ?>
<div class="teamwork-add-member">
    <label class="teamwork-add-member-label"><?= t('Add member') ?></label>
    <input type="text" class="teamwork-member-search" placeholder="<?= t('Type a name to add a member...') ?>" data-team-id="<?= (int)$team['id'] ?>">
    <div class="teamwork-member-results" style="display:none;"></div>
</div>
<?php endif ?>
