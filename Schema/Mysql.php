<?php

namespace Kanboard\Plugin\TeamWork\Schema;

use PDO;

const VERSION = 3;

function version_1(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_task_assignees (
        id INT NOT NULL AUTO_INCREMENT,
        task_id INT NOT NULL,
        user_id INT NOT NULL,
        role VARCHAR(50) DEFAULT NULL,
        position INT NOT NULL DEFAULT 0,
        source_type VARCHAR(10) NOT NULL DEFAULT \'user\',
        source_id INT DEFAULT NULL,
        created_at INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY uq_task_user (task_id, user_id),
        KEY idx_task_id (task_id),
        CONSTRAINT fk_tw_ta_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        CONSTRAINT fk_tw_ta_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_teams (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        project_id INT DEFAULT NULL,
        created_at INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        CONSTRAINT fk_tw_t_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_team_members (
        team_id INT NOT NULL,
        user_id INT NOT NULL,
        PRIMARY KEY (team_id, user_id),
        CONSTRAINT fk_tw_tm_team FOREIGN KEY (team_id) REFERENCES teamwork_teams(id) ON DELETE CASCADE,
        CONSTRAINT fk_tw_tm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function version_2(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_automation_rules (
        id INT NOT NULL AUTO_INCREMENT,
        project_id INT NOT NULL,
        column_id INT NOT NULL,
        role VARCHAR(50) NOT NULL,
        created_at INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_tw_ar_project_column (project_id, column_id),
        CONSTRAINT fk_tw_ar_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_tw_ar_column FOREIGN KEY (column_id) REFERENCES columns(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function version_3(PDO $pdo): void
{
    // Associate a team with a task as a first-class link, independent of the
    // team's members. This makes a team visible on a card even when it has no
    // members yet, and lets membership changes propagate to linked cards.
    $pdo->exec('CREATE TABLE IF NOT EXISTS teamwork_task_teams (
        task_id INT NOT NULL,
        team_id INT NOT NULL,
        created_at INT NOT NULL DEFAULT 0,
        PRIMARY KEY (task_id, team_id),
        CONSTRAINT fk_tw_tt_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        CONSTRAINT fk_tw_tt_team FOREIGN KEY (team_id) REFERENCES teamwork_teams(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Backfill links for teams that were already expanded onto tasks before
    // this version, so previously-added teams stay visible after upgrade.
    $pdo->exec('INSERT IGNORE INTO teamwork_task_teams (task_id, team_id, created_at)
        SELECT DISTINCT task_id, source_id, 0
        FROM teamwork_task_assignees
        WHERE source_type = \'team\' AND source_id IS NOT NULL');
}
