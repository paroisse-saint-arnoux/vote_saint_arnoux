<?php
// Étape 2 de la consultation : notes et commentaires par étape, livrable de l'étape 2, statut MOD, réglages.
// Conditionnelle : certaines bases de développement ont reçu ces changements avant le système de migrations.
return function (): void {
    if (!db_column_exists('architects', 'drive_url_stage2')) {
        db()->exec("ALTER TABLE architects ADD COLUMN drive_url_stage2 VARCHAR(500) NOT NULL DEFAULT '' AFTER drive_url");
    }
    // Les notes existantes sont celles de l'étape 1
    if (!db_column_exists('scores', 'stage')) {
        db()->exec('ALTER TABLE scores ADD COLUMN stage TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER architect_id,
                    DROP PRIMARY KEY, ADD PRIMARY KEY (member_id, architect_id, stage, criterion)');
    }
    db()->exec("ALTER TABLE members MODIFY role ENUM('votant', 'consultatif', 'mod', 'aucun') NOT NULL DEFAULT 'votant'");
    db()->exec('CREATE TABLE IF NOT EXISTS comments (
        member_id     INT UNSIGNED NOT NULL,
        architect_id  INT UNSIGNED NOT NULL,
        stage         TINYINT UNSIGNED NOT NULL,
        criterion     TINYINT UNSIGNED NOT NULL,
        comment       TEXT NOT NULL,
        updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (member_id, architect_id, stage, criterion),
        CONSTRAINT fk_comments_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE,
        CONSTRAINT fk_comments_architect FOREIGN KEY (architect_id) REFERENCES architects (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    db()->exec('CREATE TABLE IF NOT EXISTS settings (
        name        VARCHAR(64) NOT NULL PRIMARY KEY,
        value       TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
};
