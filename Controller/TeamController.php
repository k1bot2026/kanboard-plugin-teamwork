<?php

namespace Kanboard\Plugin\TeamWork\Controller;

use Kanboard\Controller\BaseController;

/**
 * TeamController
 *
 * Team management within project settings: create, rename, delete teams,
 * and add/remove members. Global teams are visible but not editable.
 */
class TeamController extends BaseController
{
    /**
     * Team management page — lists all teams (project-scoped + global).
     *
     * GET /teamwork/project/:project_id/teams
     */
    public function index(): void
    {
        $project = $this->getProject();
        $teams = $this->teamModel->getTeamsForProject($project['id']);

        foreach ($teams as &$team) {
            $team['members'] = $this->teamModel->getMembers((int) $team['id']);
            $team['member_count'] = count($team['members']);
            $team['is_global'] = ($team['project_id'] === null);
        }
        unset($team);

        $this->response->html($this->helper->layout->project('TeamWork:team/index', [
            'project'    => $project,
            'teams'      => $teams,
            'csrf_token' => $this->token->getReusableCSRFToken(),
            'title'      => t('Team Management'),
        ]));
    }

    /**
     * Create a new project-scoped team.
     *
     * POST /teamwork/project/:project_id/teams/create
     */
    public function create(): void
    {
        $project = $this->getProject();
        $this->checkReusableCSRFParam();

        $name = $this->request->getRawValue('name') ?: $this->request->getStringParam('name', '');
        $teamId = $this->teamModel->createTeam($name, $project['id']);

        if ($teamId === false) {
            $this->response->json(['error' => t('Team name cannot be empty.')]);
            return;
        }

        $this->response->json(['id' => $teamId, 'name' => trim($name)]);
    }

    /**
     * Rename a project-scoped team.
     *
     * POST /teamwork/project/:project_id/teams/rename
     */
    public function rename(): void
    {
        $project = $this->getProject();
        $this->checkReusableCSRFParam();

        $teamId = (int) ($this->request->getRawValue('team_id') ?: $this->request->getIntegerParam('team_id'));
        $name = $this->request->getRawValue('name') ?: $this->request->getStringParam('name', '');

        $team = $this->teamModel->getTeamById($teamId);

        if ($team === false || (int) $team['project_id'] !== $project['id']) {
            $this->response->json(['error' => t('Team not found or not editable.')]);
            return;
        }

        $result = $this->teamModel->renameTeam($teamId, $name);

        if (!$result) {
            $this->response->json(['error' => t('Team name cannot be empty.')]);
            return;
        }

        $this->response->json(['status' => 'ok']);
    }

    /**
     * Delete a project-scoped team.
     *
     * POST /teamwork/project/:project_id/teams/remove
     */
    public function remove(): void
    {
        $project = $this->getProject();
        $this->checkReusableCSRFParam();

        $teamId = (int) ($this->request->getRawValue('team_id') ?: $this->request->getIntegerParam('team_id'));
        $team = $this->teamModel->getTeamById($teamId);

        if ($team === false || (int) $team['project_id'] !== $project['id']) {
            $this->response->json(['error' => t('Team not found or not editable.')]);
            return;
        }

        $this->teamModel->deleteTeam($teamId);

        $this->response->json(['status' => 'ok']);
    }

    /**
     * Add a member to a project-scoped team.
     *
     * POST /teamwork/project/:project_id/teams/add-member
     */
    public function addMember(): void
    {
        $project = $this->getProject();
        $this->checkReusableCSRFParam();

        $teamId = (int) ($this->request->getRawValue('team_id') ?: $this->request->getIntegerParam('team_id'));
        $userId = (int) ($this->request->getRawValue('user_id') ?: $this->request->getIntegerParam('user_id'));

        $team = $this->teamModel->getTeamById($teamId);

        if ($team === false || (int) $team['project_id'] !== $project['id']) {
            $this->response->json(['error' => t('Team not found or not editable.')]);
            return;
        }

        $this->teamModel->addMember($teamId, $userId);

        $this->response->json(['members' => $this->teamModel->getMembers($teamId)]);
    }

    /**
     * Remove a member from a project-scoped team.
     *
     * POST /teamwork/project/:project_id/teams/remove-member
     */
    public function removeMember(): void
    {
        $project = $this->getProject();
        $this->checkReusableCSRFParam();

        $teamId = (int) ($this->request->getRawValue('team_id') ?: $this->request->getIntegerParam('team_id'));
        $userId = (int) ($this->request->getRawValue('user_id') ?: $this->request->getIntegerParam('user_id'));

        $team = $this->teamModel->getTeamById($teamId);

        if ($team === false || (int) $team['project_id'] !== $project['id']) {
            $this->response->json(['error' => t('Team not found or not editable.')]);
            return;
        }

        $this->teamModel->removeMember($teamId, $userId);

        $this->response->json(['members' => $this->teamModel->getMembers($teamId)]);
    }

    /**
     * Search project members for the type-ahead member picker.
     *
     * GET /teamwork/project/:project_id/teams/search-members?q=query
     */
    public function searchMembers(): void
    {
        $project = $this->getProject();
        $query = $this->request->getStringParam('q', '');

        $users = $this->projectUserRoleModel->getUsers($project['id']);
        $results = [];

        foreach ($users as $user) {
            $label = $user['name'] ?: $user['username'];
            if ($query === '' || stripos($label, $query) !== false || stripos($user['username'], $query) !== false) {
                $results[] = [
                    'id'    => (int) $user['id'],
                    'label' => $label,
                ];
            }
        }

        $this->response->json($results);
    }
}
