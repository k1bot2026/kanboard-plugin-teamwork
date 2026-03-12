<?php

namespace Kanboard\Plugin\TeamWork\Controller;

use Kanboard\Controller\BaseController;

/**
 * SettingsController
 *
 * Manages project-level TeamWork configuration:
 * enable/disable toggle and assignment mode.
 */
class SettingsController extends BaseController
{
    /**
     * Render the TeamWork settings page (enable toggle + assignment mode).
     *
     * GET /teamwork/project/:project_id/settings/assignment-mode
     */
    public function assignmentMode(): void
    {
        $project = $this->getProject();

        // Read raw value to avoid Kanboard's ?: treating "0" as falsy
        $rawEnabled = $this->db
            ->table('project_has_metadata')
            ->eq('project_id', $project['id'])
            ->eq('name', 'teamwork_enabled')
            ->findOneColumn('value');
        $enabled = ($rawEnabled === '0') ? '0' : '1';
        $mode = $this->projectMetadataModel->get(
            $project['id'], 'teamwork_assignment_mode', 'equal'
        );
        $customRoles = $this->projectMetadataModel->get(
            $project['id'], 'teamwork_custom_roles', ''
        );

        $this->response->html($this->helper->layout->project('TeamWork:settings/assignment_mode', [
            'project'      => $project,
            'enabled'      => $enabled,
            'mode'         => $mode,
            'custom_roles' => $customRoles,
            'title'        => t('TeamWork Settings'),
        ]));
    }

    /**
     * Save the TeamWork settings (enable toggle + assignment mode).
     *
     * POST /teamwork/project/:project_id/settings/save-assignment-mode
     */
    public function saveAssignmentMode(): void
    {
        $project = $this->getProject();
        $values = $this->request->getValues();

        // Enable/disable toggle (checkbox: present = '1', absent = '0')
        $enabled = !empty($values['teamwork_enabled']) ? '1' : '0';

        $mode = isset($values['assignment_mode']) ? $values['assignment_mode'] : 'equal';

        // Validate mode is one of the allowed values
        if (!in_array($mode, ['equal', 'primary_helpers', 'custom'], true)) {
            $mode = 'equal';
        }

        $customRoles = isset($values['custom_roles']) ? $values['custom_roles'] : '';

        $this->projectMetadataModel->save($project['id'], [
            'teamwork_enabled'         => $enabled,
            'teamwork_assignment_mode' => $mode,
            'teamwork_custom_roles'    => $customRoles,
        ]);

        // Retroactive migration when switching to primary + helpers mode
        if ($enabled === '1' && $mode === 'primary_helpers') {
            $this->taskAssigneeModel->applyPrimaryHelperRoles($project['id']);
        }

        $this->flash->success(t('Settings updated successfully.'));

        $this->response->redirect(
            $this->helper->url->to('SettingsController', 'assignmentMode',
                ['project_id' => $project['id'], 'plugin' => 'TeamWork'])
        );
    }
}
