<?php

namespace Kanboard\Plugin\TeamWork\Controller;

use Kanboard\Controller\BaseController;

/**
 * SettingsController
 *
 * Manages project-level assignment mode configuration.
 * Two actions: render the settings page, and save the selected mode.
 */
class SettingsController extends BaseController
{
    /**
     * Render the assignment mode settings page.
     *
     * GET /teamwork/project/:project_id/settings/assignment-mode
     */
    public function assignmentMode(): void
    {
        $project = $this->getProject();

        $mode = $this->projectMetadataModel->get(
            $project['id'], 'teamwork_assignment_mode', 'equal'
        );
        $customRoles = $this->projectMetadataModel->get(
            $project['id'], 'teamwork_custom_roles', ''
        );

        $this->response->html($this->helper->layout->project('TeamWork:settings/assignment_mode', [
            'project'      => $project,
            'mode'         => $mode,
            'custom_roles' => $customRoles,
            'title'        => t('Assignment Mode'),
        ]));
    }

    /**
     * Save the assignment mode settings.
     *
     * POST /teamwork/project/:project_id/settings/save-assignment-mode
     */
    public function saveAssignmentMode(): void
    {
        $project = $this->getProject();
        $values = $this->request->getValues();

        $mode = isset($values['assignment_mode']) ? $values['assignment_mode'] : 'equal';

        // Validate mode is one of the allowed values
        if (!in_array($mode, ['equal', 'primary_helpers', 'custom'], true)) {
            $mode = 'equal';
        }

        $customRoles = isset($values['custom_roles']) ? $values['custom_roles'] : '';

        $this->projectMetadataModel->save($project['id'], [
            'teamwork_assignment_mode' => $mode,
            'teamwork_custom_roles'    => $customRoles,
        ]);

        // Retroactive migration when switching to primary + helpers mode
        if ($mode === 'primary_helpers') {
            $this->taskAssigneeModel->applyPrimaryHelperRoles($project['id']);
        }

        $this->flash->success(t('Assignment mode updated successfully.'));

        $this->response->redirect(
            $this->helper->url->to('SettingsController', 'assignmentMode',
                ['project_id' => $project['id'], 'plugin' => 'TeamWork'])
        );
    }
}
