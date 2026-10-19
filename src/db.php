<?php

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', config('db_host'), config('db_name'));
        $pdo = new PDO($dsn, config('db_user'), config('db_pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function get_architects(): array
{
    return db_all('SELECT * FROM architects ORDER BY sort_key, id');
}

/**
 * Dossiers proposés au jury : à l'étape 1, ceux dont le lien Google Drive est renseigné ;
 * à l'étape 2, les dossiers retenus à l'issue de l'étape 1.
 */
function get_evaluable_architects(): array
{
    if (stage() === 1) {
        return db_all("SELECT * FROM architects WHERE drive_url <> '' ORDER BY sort_key, id");
    }
    $ids = stage2_architect_ids();
    return !$ids ? [] : db_all('SELECT * FROM architects WHERE id IN (' . implode(',', $ids) . ') ORDER BY sort_key, id');
}

/**
 * Dossiers retenus pour l'étape 2 : les 10 premiers du classement officiel de l'étape 1 (ex aequo inclus).
 * La sélection est figée au premier appel, pour ne plus dépendre des notes ou des statuts de l'étape 1 ;
 * elle peut être recalculée depuis les réglages.
 */
function stage2_architect_ids(bool $recompute = false): array
{
    static $ids = null;
    if ($ids !== null && !$recompute) {
        return $ids;
    }
    $saved = $recompute ? null : setting('stage2_architects');
    if ($saved !== null) {
        return $ids = array_map('intval', json_decode($saved, true) ?: []);
    }
    $ranks = with_stage(1, fn () => rank_by(array_column(ranking(), 'total', 'id')));
    $ids = array_keys(array_filter($ranks, fn ($r) => $r !== null && $r <= 10));
    save_setting('stage2_architects', json_encode($ids));
    return $ids;
}

function get_architect(int $id): ?array
{
    return db_one('SELECT * FROM architects WHERE id = ?', [$id]);
}

/** Membres du jury (votants, consultatifs puis, à l'étape 2, le MOD), hors administrateurs non-jurés. */
function get_jury(): array
{
    $roles = stage() === 1 ? "'votant', 'consultatif'" : "'votant', 'consultatif', 'mod'";
    return db_all("SELECT * FROM members WHERE role IN ($roles)
                   ORDER BY FIELD(role, 'votant', 'consultatif', 'mod'), position, name");
}

/** Membre ayant le statut MOD (un seul possible). */
function get_mod(): ?array
{
    return db_one("SELECT * FROM members WHERE role = 'mod' ORDER BY id LIMIT 1");
}

function get_members(): array
{
    return db_all("SELECT * FROM members ORDER BY FIELD(role, 'votant', 'consultatif', 'mod', 'aucun'), position, name");
}

function create_architect(array $data): int
{
    db_exec(
        'INSERT INTO architects (agency, referent, city, website, drive_url, drive_url_stage2, ' . implode(', ', array_keys(architect_checks())) . ', sort_key)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            trim($data['agency']),
            trim($data['referent'] ?? ''),
            trim($data['city'] ?? ''),
            normalize_url($data['website'] ?? ''),
            normalize_url($data['drive_url'] ?? ''),
            normalize_url($data['drive_url_stage2'] ?? ''),
            ...array_values(architect_check_levels($data)),
            random_int(0, 2_000_000_000),
        ]
    );
    return (int) db()->lastInsertId();
}
