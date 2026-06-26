<?php

namespace Kanboard\Plugin\TeamWork\Schema;

use PDO;

const VERSION = 3;

function version_1(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_task_assignees (
        id SERIAL PRIMARY KEY,
        task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        role VARCHAR(50) DEFAULT NULL,
        position INTEGER NOT NULL DEFAULT 0,
        source_type VARCHAR(10) NOT NULL DEFAULT \'user\',
        source_id INTEGER DEFAULT NULL,
        created_at INTEGER NOT NULL DEFAULT 0,
        UNIQUE (task_id, user_id)
    )');

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_teamwork_task_assignees_task_id ON teamwork_task_assignees(task_id)');

    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_teams (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        project_id INTEGER DEFAULT NULL REFERENCES projects(id) ON DELETE CASCADE,
        created_at INTEGER NOT NULL DEFAULT 0
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_team_members (
        team_id INTEGER NOT NULL REFERENCES teamwork_teams(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        PRIMARY KEY (team_id, user_id)
    )');
}

function version_2(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_automation_rules (
        id SERIAL PRIMARY KEY,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        column_id INTEGER NOT NULL REFERENCES columns(id) ON DELETE CASCADE,
        role VARCHAR(50) NOT NULL,
        created_at INTEGER NOT NULL DEFAULT 0
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
        task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
        team_id INTEGER NOT NULL REFERENCES teamwork_teams(id) ON DELETE CASCADE,
        created_at INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (task_id, team_id)
    )');

    // Backfill links for teams that were already expanded onto tasks before
    // this version, so previously-added teams stay visible after upgrade.
    $pdo->exec('INSERT INTO teamwork_task_teams (task_id, team_id, created_at)
        SELECT DISTINCT task_id, source_id, 0
        FROM teamwork_task_assignees
        WHERE source_type = \'team\' AND source_id IS NOT NULL
        ON CONFLICT DO NOTHING');
}
