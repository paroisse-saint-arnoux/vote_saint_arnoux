<?php
// Applique les migrations en attente du schéma de la base (voir src/migrations.php).
//
//   php scripts/migrate.php            applique les migrations en attente
//   php scripts/migrate.php --status   liste les migrations, sans rien appliquer
//   docker compose exec web php scripts/migrate.php

if (PHP_SAPI !== 'cli') {
    exit('CLI uniquement');
}

require dirname(__DIR__) . '/src/bootstrap.php';

$applied = applied_migrations();
foreach (migration_files() as $name => $path) {
    echo (in_array($name, $applied, true) ? '  [appliquée] ' : '  [en attente] ') . $name . "\n";
}
$pending = pending_migrations();
if (in_array('--status', $argv, true)) {
    echo count($pending) . " migration(s) en attente\n";
    exit(0);
}
if (!$pending) {
    echo "Base à jour\n";
    exit(0);
}
try {
    $done = run_migrations(fn ($line) => print($line . "\n"));
} catch (RuntimeException $ex) {
    fwrite(STDERR, $ex->getMessage() . "\n");
    exit(1);
}
echo count($done) . " migration(s) appliquée(s), base à jour\n";
