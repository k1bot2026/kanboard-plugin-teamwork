<html>
<body>
<h2><?= $this->text->e($task['title']) ?> (#<?= $task['id'] ?>)</h2>

<p><strong><?= t('You have been removed from this task.') ?></strong></p>

<?= $this->render('notification/footer', array('task' => $task)) ?>
</body>
</html>
