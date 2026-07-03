<html>
<body>
<h2><?= $this->text->e($task['title']) ?> (#<?= $task['id'] ?>)</h2>

<p><strong><?= t('You have been assigned to this task.') ?></strong></p>

<?php if (!empty($task['date_due'])): ?>
<p><?= t('Due date:') ?> <?= $this->dt->date($task['date_due']) ?></p>
<?php endif ?>

<?php if (!empty($task['description'])): ?>
    <h2><?= t('Description') ?></h2>
    <?= $this->text->markdown($task['description'], true) ?>
<?php endif ?>

<?= $this->render('notification/footer', array('task' => $task)) ?>
</body>
</html>
