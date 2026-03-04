<?php if (!empty($tw_assignees)): ?>
<div class="teamwork-avatar-stack">
    <?php if (count($tw_assignees) <= 2): ?>
        <?php foreach ($tw_assignees as $a): ?>
            <?= $this->helper->boardAvatarHelper->renderAvatar($a, 20) ?>
        <?php endforeach ?>
    <?php else: ?>
        <?= $this->helper->boardAvatarHelper->renderAvatar($tw_assignees[0], 20) ?>
        <span class="teamwork-avatar-count">+<?= count($tw_assignees) - 1 ?></span>
    <?php endif ?>
</div>
<?php endif ?>
