<?php if ($this->user->hasProjectAccess('ProjectEditController', 'show', $project['id'])): ?>
<li <?= $this->app->checkMenuSelection('SettingsController', 'assignmentMode') ?>>
    <?= $this->url->link(t('TeamWork Settings'), 'SettingsController', 'assignmentMode',
        ['project_id' => $project['id'], 'plugin' => 'TeamWork']) ?>
</li>
<?php if ($tw_enabled === '1'): ?>
<li <?= $this->app->checkMenuSelection('TeamController', 'index') ?>>
    <?= $this->url->link(t('Team Management'), 'TeamController', 'index',
        ['project_id' => $project['id'], 'plugin' => 'TeamWork']) ?>
</li>
<li <?= $this->app->checkMenuSelection('AutomationController', 'index') ?>>
    <?= $this->url->link(t('Automation Rules'), 'AutomationController', 'index',
        ['project_id' => $project['id'], 'plugin' => 'TeamWork']) ?>
</li>
<?php endif ?>
<?php endif ?>
