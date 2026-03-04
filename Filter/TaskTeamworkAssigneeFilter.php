<?php

namespace Kanboard\Plugin\TeamWork\Filter;

use Kanboard\Core\Filter\FilterInterface;
use Kanboard\Filter\BaseFilter;
use Kanboard\Model\TaskModel;
use PicoDb\Database;
use PicoDb\Table;

class TaskTeamworkAssigneeFilter extends BaseFilter implements FilterInterface
{
    /**
     * @var Database
     */
    protected $db;

    /**
     * @var int
     */
    protected $currentUserId = 0;

    /**
     * Set database connection
     *
     * @param Database $db
     * @return $this
     */
    public function setDatabase(Database $db): self
    {
        $this->db = $db;
        return $this;
    }

    /**
     * Set current user ID (for resolving "me")
     *
     * @param int $userId
     * @return $this
     */
    public function setCurrentUserId(int $userId): self
    {
        $this->currentUserId = $userId;
        return $this;
    }

    /**
     * Get filter attributes (overrides built-in assignee: filter)
     *
     * @return string[]
     */
    public function getAttributes(): array
    {
        return ['assignee'];
    }

    /**
     * Apply filter to query
     *
     * Handles: me, nobody, anybody, numeric user ID, username string.
     * Searches both native owner_id and teamwork_task_assignees table.
     *
     * @return FilterInterface
     */
    public function apply(): FilterInterface
    {
        $value = $this->value;

        if ($value === 'me') {
            return $this->applyUserFilter($this->currentUserId);
        }

        if ($value === 'nobody') {
            return $this->applyNobodyFilter();
        }

        if ($value === 'anybody') {
            return $this->applyAnybodyFilter();
        }

        if (is_numeric($value)) {
            return $this->applyUserFilter((int) $value);
        }

        // String value: look up user by username or name
        $userId = $this->findUserByName($value);

        if ($userId !== null) {
            return $this->applyUserFilter($userId);
        }

        // No matching user found: return impossible condition so no results match
        $this->query->eq(TaskModel::TABLE . '.id', 0);
        return $this;
    }

    /**
     * Filter tasks assigned to a specific user (native owner OR TeamWork assignee)
     *
     * @param int $userId
     * @return FilterInterface
     */
    private function applyUserFilter(int $userId): FilterInterface
    {
        $this->query->beginOr()
            ->eq(TaskModel::TABLE . '.owner_id', $userId)
            ->inSubquery(
                TaskModel::TABLE . '.id',
                $this->db->table('teamwork_task_assignees')
                    ->columns('teamwork_task_assignees.task_id')
                    ->eq('teamwork_task_assignees.user_id', $userId)
            )
            ->closeOr();

        return $this;
    }

    /**
     * Filter tasks with no assignees (no native owner AND no TeamWork assignees)
     *
     * @return FilterInterface
     */
    private function applyNobodyFilter(): FilterInterface
    {
        $this->query->eq(TaskModel::TABLE . '.owner_id', 0);
        $this->query->notInSubquery(
            TaskModel::TABLE . '.id',
            $this->db->table('teamwork_task_assignees')
                ->columns('teamwork_task_assignees.task_id')
                ->distinct()
        );

        return $this;
    }

    /**
     * Filter tasks that have any assignee (native owner OR any TeamWork assignee)
     *
     * @return FilterInterface
     */
    private function applyAnybodyFilter(): FilterInterface
    {
        $this->query->beginOr()
            ->gt(TaskModel::TABLE . '.owner_id', 0)
            ->inSubquery(
                TaskModel::TABLE . '.id',
                $this->db->table('teamwork_task_assignees')
                    ->columns('teamwork_task_assignees.task_id')
                    ->distinct()
            )
            ->closeOr();

        return $this;
    }

    /**
     * Look up a user by username or name (case-insensitive)
     *
     * @param string $name
     * @return int|null
     */
    private function findUserByName(string $name): ?int
    {
        $user = $this->db->table('users')
            ->columns('id')
            ->beginOr()
            ->ilike('username', $name)
            ->ilike('name', '%' . $name . '%')
            ->closeOr()
            ->findOne();

        if (!empty($user)) {
            return (int) $user['id'];
        }

        return null;
    }
}
