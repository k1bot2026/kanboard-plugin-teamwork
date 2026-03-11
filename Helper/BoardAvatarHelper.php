<?php

namespace Kanboard\Plugin\TeamWork\Helper;

use Kanboard\Core\Base;

/**
 * BoardAvatarHelper
 *
 * Batch-loads all task assignees per project in a single query, then serves
 * cached slices per task. Renders avatar HTML for board card display.
 */
class BoardAvatarHelper extends Base
{
    /**
     * In-memory cache indexed by project_id.
     * Each entry is an array indexed by task_id containing assignee rows.
     *
     * @var array
     */
    private static $cache = [];

    /**
     * Get assignees for a task, using the batch-loaded project cache.
     *
     * On first call for a project, loads ALL assignees for every task in that
     * project via a single query. Subsequent calls return cached slices.
     *
     * @param int $taskId
     * @param int $projectId
     * @return array
     */
    public function getAssigneesForTask(int $taskId, int $projectId): array
    {
        if (!isset(self::$cache[$projectId])) {
            $this->loadAllAssignees($projectId);
        }

        return self::$cache[$projectId][$taskId] ?? [];
    }

    /**
     * Render a single avatar element (img or letter-circle).
     *
     * @param array $user  Assignee row with user_id, name, username, email, avatar_path
     * @param int   $size  Avatar diameter in pixels
     * @return string HTML
     */
    public function renderAvatar(array $user, int $size = 20): string
    {
        $displayName = !empty($user['name']) ? $user['name'] : $user['username'];
        $escapedName = $this->helper->text->e($displayName);

        if (!empty($user['avatar_path'])) {
            $url = $this->helper->url->to(
                'AvatarFileController',
                'image',
                [
                    'user_id' => $user['user_id'],
                    'hash'    => md5($user['avatar_path'] . $size),
                    'size'    => $size,
                ]
            );

            return sprintf(
                '<img class="teamwork-avatar" src="%s" width="%d" height="%d" alt="%s" title="%s">',
                $this->helper->text->e($url),
                $size,
                $size,
                $escapedName,
                $escapedName
            );
        }

        $initials = $this->getInitials($displayName);
        list($r, $g, $b) = $this->nameToColor($displayName);
        $fontSize = (int) round($size * 0.45);

        return sprintf(
            '<span class="teamwork-avatar teamwork-avatar-letter" style="background-color:rgb(%d,%d,%d);width:%dpx;height:%dpx;line-height:%dpx;font-size:%dpx;" title="%s">%s</span>',
            $r,
            $g,
            $b,
            $size,
            $size,
            $size,
            $fontSize,
            $escapedName,
            $this->helper->text->e($initials)
        );
    }

    /**
     * Load all assignees for every task in a project with a single query.
     *
     * Joins teamwork_task_assignees with users and tasks, filtered by project_id.
     * Results are grouped by task_id into the static cache.
     *
     * @param int $projectId
     * @return void
     */
    private function loadAllAssignees(int $projectId): void
    {
        $rows = $this->db
            ->table('teamwork_task_assignees')
            ->columns(
                'teamwork_task_assignees.id',
                'teamwork_task_assignees.user_id',
                'teamwork_task_assignees.role',
                'teamwork_task_assignees.position',
                'teamwork_task_assignees.task_id',
                'users.username',
                'users.name',
                'users.email',
                'users.avatar_path'
            )
            ->join('users', 'id', 'user_id', 'teamwork_task_assignees')
            ->join('tasks', 'id', 'task_id', 'teamwork_task_assignees')
            ->eq('tasks.project_id', $projectId)
            ->asc('teamwork_task_assignees.position')
            ->findAll();

        $indexed = [];

        foreach ($rows as $row) {
            $taskId = (int) $row['task_id'];
            $indexed[$taskId][] = $row;
        }

        self::$cache[$projectId] = $indexed;
    }

    /**
     * Extract initials from a display name.
     *
     * Two-word names: first char of first + first char of last.
     * Single-word names: first two chars.
     * UTF-8 safe via mb_ functions.
     *
     * @param string $name
     * @return string
     */
    private function getInitials(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/', $name);

        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    /**
     * Convert a name to an RGB color using the BKDR hash algorithm.
     *
     * Matches Kanboard's LetterAvatarProvider algorithm for color consistency.
     *
     * @param string $name
     * @return array [r, g, b]
     */
    private function nameToColor(string $name): array
    {
        $hash = 0;
        $len = mb_strlen($name);

        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash * 131 + mb_ord(mb_substr($name, $i, 1))) & 0x7FFFFFFF;
        }

        $h = $hash % 360;
        $sValues = [0.35, 0.5, 0.65];
        $lValues = [0.35, 0.5, 0.65];
        $s = $sValues[($hash >> 8) % 3];
        $l = $lValues[($hash >> 16) % 3];

        return $this->hslToRgb($h, $s, $l);
    }

    /**
     * Convert HSL to RGB.
     *
     * @param float $h Hue (0-360)
     * @param float $s Saturation (0-1)
     * @param float $l Lightness (0-1)
     * @return array [r, g, b] as integers 0-255
     */
    private function hslToRgb(float $h, float $s, float $l): array
    {
        $h = $h / 360.0;

        if ($s == 0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $this->hueToRgb($p, $q, $h + 1.0 / 3.0);
            $g = $this->hueToRgb($p, $q, $h);
            $b = $this->hueToRgb($p, $q, $h - 1.0 / 3.0);
        }

        return [
            (int) round($r * 255),
            (int) round($g * 255),
            (int) round($b * 255),
        ];
    }

    /**
     * Helper for HSL to RGB conversion.
     *
     * @param float $p
     * @param float $q
     * @param float $t
     * @return float
     */
    private function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1.0 / 6.0) {
            return $p + ($q - $p) * 6.0 * $t;
        }
        if ($t < 1.0 / 2.0) {
            return $q;
        }
        if ($t < 2.0 / 3.0) {
            return $p + ($q - $p) * (2.0 / 3.0 - $t) * 6.0;
        }

        return $p;
    }
}
