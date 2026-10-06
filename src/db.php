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

/** Dossiers proposés au jury : ceux dont le lien Google Drive est renseigné. */
function get_evaluable_architects(): array
{
    return db_all("SELECT * FROM architects WHERE drive_url <> '' ORDER BY sort_key, id");
}

function get_architect(int $id): ?array
{
    return db_one('SELECT * FROM architects WHERE id = ?', [$id]);
}

/** Membres du jury (votants puis consultatifs), hors administrateurs non-jurés. */
function get_jury(): array
{
    return db_all("SELECT * FROM members WHERE role IN ('votant', 'consultatif')
                   ORDER BY FIELD(role, 'votant', 'consultatif'), position, name");
}

function get_members(): array
{
    return db_all("SELECT * FROM members ORDER BY FIELD(role, 'votant', 'consultatif', 'aucun'), position, name");
}

function create_architect(array $data): int
{
    db_exec(
        'INSERT INTO architects (agency, referent, city, website, drive_url, ' . implode(', ', array_keys(architect_checks())) . ', sort_key)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            trim($data['agency']),
            trim($data['referent'] ?? ''),
            trim($data['city'] ?? ''),
            normalize_url($data['website'] ?? ''),
            normalize_url($data['drive_url'] ?? ''),
            ...array_values(architect_check_levels($data)),
            random_int(0, 2_000_000_000),
        ]
    );
    return (int) db()->lastInsertId();
}
