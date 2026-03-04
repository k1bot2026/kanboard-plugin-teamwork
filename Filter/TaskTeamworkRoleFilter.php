<?php

namespace Kanboard\Plugin\TeamWork\Filter;

use Kanboard\Core\Filter\FilterInterface;
use Kanboard\Filter\BaseFilter;
use Kanboard\Model\TaskModel;
use PicoDb\Database;
use PicoDb\Table;

class TaskTeamworkRoleFilter extends BaseFilter implements FilterInterface
{
    /**
     * @var Database
     */
    protected $db;

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
     * Get filter attributes (new role: filter, no collision with built-in)
     *
     * @return string[]
     */
    public function getAttributes(): array
    {
        return ['role'];
    }

    /**
     * Apply filter to query
     *
     * Filters tasks by assignment role using case-insensitive matching.
     * Example: role:reviewer matches tasks with any assignee having "Reviewer" role.
     *
     * @return FilterInterface
     */
    public function apply(): FilterInterface
    {
        $this->query->inSubquery(
            TaskModel::TABLE . '.id',
            $this->db->table('teamwork_task_assignees')
                ->columns('teamwork_task_assignees.task_id')
                ->ilike('teamwork_task_assignees.role', $this->value)
        );

        return $this;
    }
}
