<?php
// Migrations du schéma de la base.
//
// sql/schema.sql contient le schéma initial (installation neuve). Toute modification ultérieure du schéma
// se fait par un fichier ajouté dans sql/migrations/, appliqué une seule fois, dans l'ordre des noms :
//   NNN_description.sql : requêtes SQL, chacune terminée par « ; » en fin de ligne ;
//   NNN_description.php : fichier renvoyant une fonction, pour les migrations qui doivent d'abord
//                         vérifier l'état de la base (voir db_column_exists()).
// Les migrations appliquées sont enregistrées dans la table migrations. Tant qu'il en reste à appliquer,
// l'application affiche une page de maintenance (voir public/index.php).
// Application : php scripts/migrate.php, ou depuis cette page de maintenance par un administrateur.

/** Fichiers de migration, triés : [nom => chemin]. */
function migration_files(): array
{
    $files = [];
    foreach (glob(APP_ROOT . '/sql/migrations/*') as $path) {
        if (preg_match('/\.(sql|php)$/', $path)) {
            $files[basename($path)] = $path;
        }
    }
    ksort($files, SORT_STRING);
    return $files;
}

/** Noms des migrations déjà appliquées. */
function applied_migrations(): array
{
    try {
        return array_column(db_all('SELECT name FROM migrations'), 'name');
    } catch (PDOException $ex) {
        if (($ex->errorInfo[1] ?? null) == 1146) { // table absente : aucune migration appliquée
            return [];
        }
        throw $ex;
    }
}

/** Migrations restant à appliquer : [nom => chemin]. */
function pending_migrations(): array
{
    return array_diff_key(migration_files(), array_flip(applied_migrations()));
}

/**
 * Applique les migrations en attente, dans l'ordre ; s'arrête à la première erreur (RuntimeException).
 * Les modifications de structure ne pouvant être annulées sous MySQL, une migration en échec peut avoir
 * été appliquée en partie : à corriger avant de relancer.
 * @return string[] noms des migrations appliquées
 */
function run_migrations(?callable $log = null): array
{
    db()->exec('CREATE TABLE IF NOT EXISTS migrations (
        name        VARCHAR(190) NOT NULL PRIMARY KEY,
        applied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    if (!db_one("SELECT GET_LOCK('vote_migrations', 30) AS ok")['ok']) {
        throw new RuntimeException('Une autre mise à jour de la base est déjà en cours.');
    }
    $applied = [];
    try {
        foreach (pending_migrations() as $name => $path) {
            $log && $log("Migration {$name}…");
            try {
                apply_migration($path);
            } catch (Throwable $ex) {
                throw new RuntimeException("Échec de la migration {$name} : " . $ex->getMessage(), 0, $ex);
            }
            db_exec('INSERT INTO migrations (name) VALUES (?)', [$name]);
            $applied[] = $name;
        }
    } finally {
        db_one("SELECT RELEASE_LOCK('vote_migrations')");
    }
    return $applied;
}

function apply_migration(string $path): void
{
    if (str_ends_with($path, '.php')) {
        (require $path)();
        return;
    }
    foreach (preg_split('/;\s*$/m', file_get_contents($path)) as $sql) {
        if (trim(preg_replace('/^\s*--.*$/m', '', $sql)) !== '') {
            db()->exec($sql);
        }
    }
}

function db_column_exists(string $table, string $column): bool
{
    return (bool) db_one(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );
}
