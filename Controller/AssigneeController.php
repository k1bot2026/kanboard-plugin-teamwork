<?php

namespace Kanboard\Plugin\TeamWork\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Event\GenericEvent;

/**
 * AssigneeController
 *
 * HTTP handlers for the multi-assignee workflow: search, add, remove
 * (individual), removeGroup (all members from a Kanboard group), and
 * removeTeam (all members from a plugin team).
 */
class AssigneeController extends BaseController
{
    /**
     * Search users, groups, and teams for the type-ahead picker.
     *
     * GET /teamwork/search/assignees?project_id=X&q=query
     * Returns JSON array of {type, id, label} objects.
     */
    public function search(): void
    {
        $project = $this->getProject();
        $query = $this->request->getStringParam('q', '');

        $results = [];

        // Users: filter project members by name/username
        $users = $this->projectUserRoleModel->getUsers($project['id']);
        foreach ($users as $user) {
            $label = $user['name'] ?: $user['username'];
            if ($query === '' || stripos($label, $query) !== false || stripos($user['username'], $query) !== false) {
                $results[] = [
                    'type'  => 'user',
                    'id'    => (int) $user['id'],
                    'label' => $label,
                ];
            }
        }

        // Groups: Kanboard native groups
        $groups = $this->groupModel->search($query);
        foreach ($groups as $group) {
            $results[] = [
                'type'  => 'group',
                'id'    => (int) $group['id'],
                'label' => $group['name'],
            ];
        }

        // Teams: plugin-defined teams scoped to this project
        $teams = $this->taskAssigneeModel->searchTeams($project['id'], $query);
        foreach ($teams as $team) {
            $results[] = [
                'type'  => 'team',
                'id'    => (int) $team['id'],
                'label' => $team['name'],
            ];
        }

        $this->response->json($results);
    }

    /**
     * Add an assignee (user, group, or team) to a task.
     *
     * POST /teamwork/task/:task_id/assignees/add
     * Body: csrf_token, type (user|group|team), entity_id
     */
    public function add(): void
    {
        $task = $this->getTask();
        $this->checkReusableCSRFParam();

        $type = $this->request->getRawValue('type') ?: $this->request->getStringParam('type', 'user');
        $entityId = (int) ($this->request->getRawValue('entity_id') ?: $this->request->getIntegerParam('entity_id'));

        switch ($type) {
            case 'group':
                $this->taskAssigneeModel->addGroup($task['id'], $entityId);
                break;
            case 'team':
                $this->taskAssigneeModel->addTeam($task['id'], $entityId);
                break;
            default:
                $added = $this->taskAssigneeModel->addAssignee($task['id'], $entityId);
                if ($added) {
                    $this->dispatcher->dispatch(
                        new GenericEvent([
                            'task'    => $this->taskFinderModel->getById($task['id']),
                            'user_id' => $entityId,
                        ]),
                        'teamwork.assignee.add'
                    );
                }
                break;
        }

        if ($this->request->isAjax()) {
            $this->response->json([
                'assignees' => $this->taskAssigneeModel->getAssigneesForTask($task['id']),
            ]);
        } else {
            $this->response->redirect(
                $this->helper->url->to('TaskViewController', 'show', [
                    'task_id'    => $task['id'],
                    'project_id' => $task['project_id'],
                ])
            );
        }
    }

    /**
     * Remove a single assignee by their record ID.
     *
     * POST /teamwork/task/:task_id/assignees/remove/:assignee_id
     * Body: csrf_token
     */
    public function remove(): void
    {
        $task = $this->getTask();
        $this->checkReusableCSRFParam();

        $assigneeId = $this->request->getIntegerParam('assignee_id');

        // Capture assignee record before deletion for notification dispatch
        $assigneeRecord = $this->db->table('teamwork_task_assignees')
            ->eq('id', $assigneeId)
            ->eq('task_id', $task['id'])
            ->findOne();

        $removed = $this->taskAssigneeModel->removeAssignee($assigneeId, $task['id']);

        if ($removed && $assigneeRecord) {
            $this->dispatcher->dispatch(
                new GenericEvent([
                    'task'    => $this->taskFinderModel->getById($task['id']),
                    'user_id' => (int) $assigneeRecord['user_id'],
                ]),
                'teamwork.assignee.remove'
            );
        }

        if ($this->request->isAjax()) {
            $this->response->json([
                'status'    => 'ok',
                'assignees' => $this->taskAssigneeModel->getAssigneesForTask($task['id']),
            ]);
        } else {
            $this->response->redirect(
                $this->helper->url->to('TaskViewController', 'show', [
                    'task_id'    => $task['id'],
                    'project_id' => $task['project_id'],
                ])
            );
        }
    }

    /**
     * Remove all assignees from a specific Kanboard group.
     *
     * POST /teamwork/task/:task_id/assignees/remove-group/:group_id
     * Body: csrf_token
     */
    public function removeGroup(): void
    {
        $task = $this->getTask();
        $this->checkReusableCSRFParam();

        $groupId = $this->request->getIntegerParam('group_id');
        $count = $this->taskAssigneeModel->removeGroup($task['id'], $groupId);

        if ($this->request->isAjax()) {
            $this->response->json([
                'status'    => 'ok',
                'removed'   => $count,
                'assignees' => $this->taskAssigneeModel->getAssigneesForTask($task['id']),
            ]);
        } else {
            $this->response->redirect(
                $this->helper->url->to('TaskViewController', 'show', [
                    'task_id'    => $task['id'],
                    'project_id' => $task['project_id'],
                ])
            );
        }
    }

    /**
     * Update the role for a specific assignee on a task.
     *
     * POST /teamwork/task/:task_id/assignees/update-role
     * Body: csrf_token, assignee_id, role
     */
    public function updateRole(): void
    {
        $task = $this->getTask();
        $this->checkReusableCSRFParam();

        $assigneeId = (int) ($this->request->getRawValue('assignee_id') ?: $this->request->getIntegerParam('assignee_id'));
        $role = $this->request->getRawValue('role') ?: $this->request->getStringParam('role', '');

        $this->taskAssigneeModel->updateRole($assigneeId, $task['id'], $role ?: null);

        if ($this->request->isAjax()) {
            $this->response->json([
                'assignees' => $this->taskAssigneeModel->getAssigneesForTask($task['id']),
            ]);
        } else {
            $this->response->redirect(
                $this->helper->url->to('TaskViewController', 'show', [
                    'task_id'    => $task['id'],
                    'project_id' => $task['project_id'],
                ])
            );
        }
    }

    /**
     * Remove all assignees from a specific plugin team.
     *
     * POST /teamwork/task/:task_id/assignees/remove-team/:team_id
     * Body: csrf_token
     */
    public function removeTeam(): void
    {
        $task = $this->getTask();
        $this->checkReusableCSRFParam();

        $teamId = $this->request->getIntegerParam('team_id');
        $count = $this->taskAssigneeModel->removeTeam($task['id'], $teamId);

        if ($this->request->isAjax()) {
            $this->response->json([
                'status'    => 'ok',
                'removed'   => $count,
                'assignees' => $this->taskAssigneeModel->getAssigneesForTask($task['id']),
            ]);
        } else {
            $this->response->redirect(
                $this->helper->url->to('TaskViewController', 'show', [
                    'task_id'    => $task['id'],
                    'project_id' => $task['project_id'],
                ])
            );
        }
    }
}
