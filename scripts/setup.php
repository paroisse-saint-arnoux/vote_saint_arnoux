<?php
// Initialisation : crée les tables, ajoute le jury initial et importe les architectes.
// Idempotent : peut être relancé sans risque.
//
//   docker compose exec web php scripts/setup.php
//   php scripts/setup.php [chemin/vers/fichier.tsv]      (O2Switch, via SSH)

if (PHP_SAPI !== 'cli') {
    exit('CLI uniquement');
}

require dirname(__DIR__) . '/src/bootstrap.php';

db()->exec(file_get_contents(APP_ROOT . '/sql/schema.sql'));

// Migrations : colonnes ajoutées après la création initiale des tables
$existing = array_column(db_all(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'architects'"
), 'COLUMN_NAME');
foreach (array_keys(architect_checks()) as $col) {
    if (!in_array($col, $existing, true)) {
        db()->exec("ALTER TABLE architects ADD COLUMN {$col} TINYINT UNSIGNED NOT NULL DEFAULT 0");
        echo "Colonne architects.{$col} ajoutée\n";
    }
}
echo "Schéma OK\n";

// Liste du jury : config/jury.local.php (non versionné), sinon l'exemple fourni.
$juryFile = APP_ROOT . '/config/jury.local.php';
if (!is_file($juryFile)) {
    echo "config/jury.local.php absent : utilisation de config/jury.example.php\n";
    $juryFile = APP_ROOT . '/config/jury.example.php';
}
$jury = require $juryFile;

$count = (int) db()->query('SELECT COUNT(*) FROM members')->fetchColumn();
if ($count === 0) {
    foreach ($jury as $i => [$name, $email, $role, $admin]) {
        db_exec('INSERT INTO members (name, email, role, is_admin, position) VALUES (?, ?, ?, ?, ?)',
            [$name, $email, $role, $admin, $i]);
    }
    echo count($jury) . " membres ajoutés\n";
} else {
    echo "Jury déjà présent ($count membres), inchangé\n";
}

$file = $argv[1] ?? (glob(APP_ROOT . '/architectes/*.tsv')[0] ?? null);
if ($file && is_file($file)) {
    $result = import_architects(parse_architects_tsv(file_get_contents($file)));
    echo "Architectes : {$result['created']} créés, {$result['updated']} complétés (" . basename($file) . ")\n";
} else {
    echo "Aucun fichier TSV trouvé, import ignoré\n";
}
