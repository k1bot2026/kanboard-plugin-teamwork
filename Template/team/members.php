<ul class="teamwork-member-list">
    <?php foreach ($team['members'] as $member): ?>
    <li class="teamwork-member-item" data-user-id="<?= (int)$member['user_id'] ?>">
        <span><?= $this->text->e($member['name'] ?: $member['username']) ?></span>
        <?php if (!$team['is_global']): ?>
        <a href="#" class="teamwork-member-remove" title="<?= t('Remove') ?>"><i class="fa fa-times"></i></a>
        <?php endif ?>
    </li>
    <?php endforeach ?>
</ul>
<?php if (!$team['is_global']): ?>
<div class="teamwork-add-member">
    <input type="text" class="teamwork-member-search" placeholder="<?= t('Add member...') ?>" data-team-id="<?= (int)$team['id'] ?>">
    <div class="teamwork-member-results" style="display:none;"></div>
</div>
<?php endif ?>
