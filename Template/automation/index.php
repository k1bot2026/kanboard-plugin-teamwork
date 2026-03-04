<div class="page-header">
    <h2><?= t('Automation Rules') ?></h2>
</div>

<p class="alert alert-info">
    <?= t('When a task enters a column, automatically set a role on all existing assignees.') ?>
</p>

<?php if (!empty($rules)): ?>
<table class="table-striped table-scrolling">
    <thead>
        <tr>
            <th><?= t('Column') ?></th>
            <th><?= t('Role') ?></th>
            <th><?= t('Action') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rules as $rule): ?>
        <tr>
            <td><?= $this->text->e($rule['column_name']) ?></td>
            <td><?= $this->text->e($rule['role']) ?></td>
            <td>
                <?= $this->url->link(
                    t('Remove'),
                    'AutomationController',
                    'removeRule',
                    ['project_id' => $project['id'], 'rule_id' => $rule['id'], 'plugin' => 'TeamWork'],
                    true,
                    'confirm-action'
                ) ?>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php else: ?>
<p class="alert"><?= t('No automation rules configured for this project.') ?></p>
<?php endif ?>

<div class="page-header">
    <h2><?= t('Add Automation Rule') ?></h2>
</div>

<form method="post" action="<?= $this->url->href('AutomationController', 'addRule', ['project_id' => $project['id'], 'plugin' => 'TeamWork']) ?>">
    <?= $this->form->csrf() ?>
    <div class="form-inline">
        <?= t('When task enters') ?>
        <?= $this->form->select('column_id', $columns, [], [], ['required']) ?>
        <?= t('set role to') ?>
        <?= $this->form->select('role', $roles, [], [], ['required']) ?>
        <button type="submit" class="btn btn-blue"><?= t('Add Rule') ?></button>
    </div>
</form>
