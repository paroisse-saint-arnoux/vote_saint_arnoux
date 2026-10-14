<?php
// Points de vigilance du MOD sur chaque dossier (déjà présents dans les bases créées depuis schema.sql).
return function (): void {
    foreach (['check_grouping', 'check_financial', 'check_insurance'] as $col) {
        if (!db_column_exists('architects', $col)) {
            db()->exec("ALTER TABLE architects ADD COLUMN {$col} TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER drive_url");
        }
    }
};
