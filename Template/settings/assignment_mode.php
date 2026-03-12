<div class="page-header">
    <h2><?= t('TeamWork Settings') ?></h2>
</div>

<form method="post" action="<?= $this->url->href('SettingsController', 'saveAssignmentMode', ['project_id' => $project['id'], 'plugin' => 'TeamWork']) ?>">
    <?= $this->form->csrf() ?>

    <fieldset>
        <legend><?= t('Enable / Disable') ?></legend>
        <div class="teamwork-mode-option">
            <label>
                <input type="checkbox" name="teamwork_enabled" value="1"
                    <?= $enabled === '1' ? 'checked="checked"' : '' ?>>
                <?= t('Enable multi-person task assignment for this project') ?>
            </label>
            <p class="form-help"><?= t('When disabled, this project uses standard Kanboard single-assignee behavior.') ?></p>
        </div>
    </fieldset>

    <fieldset>
        <legend><?= t('Assignment Mode') ?></legend>
        <p class="form-help"><?= t('How should roles work for this project?') ?></p>

        <div class="teamwork-mode-option">
            <label>
                <input type="radio" name="assignment_mode" value="equal"
                    <?= $mode === 'equal' ? 'checked="checked"' : '' ?>>
                <?= t('Equal Assignees') ?>
            </label>
            <p class="form-help"><?= t('All assignees have the same status. No roles are displayed.') ?></p>
        </div>

        <div class="teamwork-mode-option">
            <label>
                <input type="radio" name="assignment_mode" value="primary_helpers"
                    <?= $mode === 'primary_helpers' ? 'checked="checked"' : '' ?>>
                <?= t('Primary + Helpers') ?>
            </label>
            <p class="form-help"><?= t('First assignee is the Primary owner. Others are Helpers.') ?></p>
        </div>

        <div class="teamwork-mode-option">
            <label>
                <input type="radio" name="assignment_mode" value="custom"
                    <?= $mode === 'custom' ? 'checked="checked"' : '' ?>>
                <?= t('Custom Roles') ?>
            </label>
            <p class="form-help"><?= t('Define your own role names. Built-in roles (Primary, Helper, Reviewer) are always available.') ?></p>
        </div>

        <div class="teamwork-custom-roles-input">
            <label for="custom_roles"><?= t('Custom role names (comma-separated)') ?></label>
            <input type="text" id="custom_roles" name="custom_roles"
                   value="<?= $this->text->e($custom_roles) ?>"
                   placeholder="<?= t('e.g., QA, Designer, Stakeholder') ?>">
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-blue"><?= t('Save') ?></button>
    </div>
</form>
