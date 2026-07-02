<?php if (!empty($tw_assignees)): ?>
<div class="teamwork-avatar-stack">
    <?php if (count($tw_assignees) <= 2): ?>
        <?php foreach ($tw_assignees as $a): ?>
            <?= $this->helper->boardAvatarHelper->renderAvatar($a, 20) ?>
        <?php endforeach ?>
    <?php else: ?>
        <?= $this->helper->boardAvatarHelper->renderAvatar($tw_assignees[0], 20) ?>
        <?php
        // Hover on the +N badge lists the collapsed assignees so you can see
        // everyone without opening the card.
        $tw_hidden_names = [];
        foreach (array_slice($tw_assignees, 1) as $tw_hidden) {
            $tw_hidden_names[] = !empty($tw_hidden['name']) ? $tw_hidden['name'] : $tw_hidden['username'];
        }
        ?>
        <span class="teamwork-avatar-count" title="<?= $this->text->e(implode(', ', $tw_hidden_names)) ?>">+<?= count($tw_assignees) - 1 ?></span>
    <?php endif ?>
</div>
<?php endif ?>
