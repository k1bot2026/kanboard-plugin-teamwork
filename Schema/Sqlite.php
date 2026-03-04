<?php

namespace Kanboard\Plugin\TeamWork\Schema;

use PDO;

const VERSION = 2;

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
