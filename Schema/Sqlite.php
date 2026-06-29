<?php

namespace Kanboard\Plugin\TeamWork\Schema;

use PDO;

const VERSION = 3;

function version_1(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_task_assignees (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        task_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        role TEXT DEFAULT NULL,
        position INTEGER NOT NULL DEFAULT 0,
        source_type TEXT NOT NULL DEFAULT \'user\',
        source_id INTEGER DEFAULT NULL,
        created_at INTEGER NOT NULL DEFAULT 0,
        UNIQUE (task_id, user_id),
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )');

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_teamwork_task_assignees_task_id ON teamwork_task_assignees(task_id)');

    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_teams (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        project_id INTEGER DEFAULT NULL,
        created_at INTEGER NOT NULL DEFAULT 0,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_team_members (
        team_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        PRIMARY KEY (team_id, user_id),
        FOREIGN KEY (team_id) REFERENCES teamwork_teams(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )');
}

function version_2(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_automation_rules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        column_id INTEGER NOT NULL,
        role TEXT NOT NULL,
        created_at INTEGER NOT NULL DEFAULT 0,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (column_id) REFERENCES columns(id) ON DELETE CASCADE
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_teamwork_ar_project_column
        ON teamwork_automation_rules(project_id, column_id)');
}

function version_3(PDO $pdo): void
{
    // Associate a team with a task as a first-class link, independent of the
    // team's members. This makes a team visible on a card even when it has no
    // members yet, and lets membership changes propagate to linked cards.
    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_task_teams (
        task_id INTEGER NOT NULL,
        team_id INTEGER NOT NULL,
        created_at INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (task_id, team_id),
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (team_id) REFERENCES teamwork_teams(id) ON DELETE CASCADE
    )');

    // Backfill links for teams that were already expanded onto tasks before
    // this version, so previously-added teams stay visible after upgrade.
    $pdo->exec('INSERT OR IGNORE INTO teamwork_task_teams (task_id, team_id, created_at)
        SELECT DISTINCT task_id, source_id, 0
        FROM teamwork_task_assignees
        WHERE source_type = \'team\' AND source_id IS NOT NULL
          AND source_id IN (SELECT id FROM teamwork_teams)
          AND task_id IN (SELECT id FROM tasks)');
}
