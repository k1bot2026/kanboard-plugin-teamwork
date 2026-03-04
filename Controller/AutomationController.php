<?php

namespace Kanboard\Plugin\TeamWork\Controller;

use Kanboard\Controller\BaseController;

/**
 * AutomationController
 *
 * Manages column-transition automation rules from project settings.
 * Project admins can add rules that automatically set a role on all
 * existing assignees when a task enters a specific column.
 */
class AutomationController extends BaseController
{
    /**
     * Render the automation rules settings page.
     *
     * GET /teamwork/project/:project_id/automation
     */
    public function index(): void
    {
        $project = $this->getProject();

        $rules = $this->automationRuleModel->getRulesForProject($project['id']);

        // Build column dropdown: column_id => title
        $allColumns = $this->columnModel->getAll($project['id']);
        $columns = [];
        foreach ($allColumns as $col) {
            $columns[$col['id']] = $col['title'];
        }

        // Build role dropdown based on assignment mode
        $roles = $this->getAvailableRoles($project['id']);

        $this->response->html($this->helper->layout->project('TeamWork:automation/index', [
            'project' => $project,
            'rules'   => $rules,
            'columns' => $columns,
            'roles'   => $roles,
            'title'   => t('Automation Rules'),
        ]));
    }

    /**
     * Add a new automation rule.
     *
     * POST /teamwork/project/:project_id/automation/add-rule
     */
    public function addRule(): void
    {
        $project = $this->getProject();
        $values = $this->request->getValues();

        $columnId = isset($values['column_id']) ? (int) $values['column_id'] : 0;
        $role     = isset($values['role']) ? $values['role'] : '';

        // Validate that column belongs to this project
        $column = $this->columnModel->getById($columnId);
        if (empty($column) || (int) $column['project_id'] !== (int) $project['id']) {
            $this->flash->failure(t('Invalid column selected.'));
        } else if ($role === '') {
            $this->flash->failure(t('Please select a role.'));
        } else {
            $this->automationRuleModel->createRule($project['id'], $columnId, $role);
            $this->flash->success(t('Automation rule added successfully.'));
        }

        $this->response->redirect(
            $this->helper->url->to('AutomationController', 'index',
                ['project_id' => $project['id'], 'plugin' => 'TeamWork'])
        );
    }

    /**
     * Remove an automation rule.
     *
     * GET /teamwork/project/:project_id/automation/remove-rule?rule_id=X
     */
    public function removeRule(): void
    {
        $project = $this->getProject();
        $this->checkCSRFParam();

        $ruleId = $this->request->getIntegerParam('rule_id');

        $this->automationRuleModel->removeRule($ruleId, $project['id']);
        $this->flash->success(t('Automation rule removed successfully.'));

        $this->response->redirect(
            $this->helper->url->to('AutomationController', 'index',
                ['project_id' => $project['id'], 'plugin' => 'TeamWork'])
        );
    }

    /**
     * Get available roles based on the project's assignment mode.
     *
     * @param int $projectId
     * @return array role_name => role_name
     */
    private function getAvailableRoles(int $projectId): array
    {
        $mode = $this->projectMetadataModel->get(
            $projectId, 'teamwork_assignment_mode', 'equal'
        );

        $builtIn = ['Primary', 'Helper', 'Reviewer'];

        if ($mode === 'primary_helpers') {
            $roleList = ['Primary', 'Helper'];
        } elseif ($mode === 'custom') {
            $customRolesStr = $this->projectMetadataModel->get(
                $projectId, 'teamwork_custom_roles', ''
            );
            $customRoles = array_filter(array_map('trim', explode(',', $customRolesStr)));
            $roleList = array_unique(array_merge($builtIn, $customRoles));
        } else {
            // 'equal' mode
            $roleList = $builtIn;
        }

        $roles = [];
        foreach ($roleList as $r) {
            $roles[$r] = $r;
        }

        return $roles;
    }
}
