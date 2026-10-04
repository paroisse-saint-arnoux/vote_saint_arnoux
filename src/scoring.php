<?php
// Calcul des scores.
//
// - Chaque critère est noté de 0 à 5 ; un critère « Non évalué » est simplement absent.
// - Score pondéré sur 100 = Σ(poids × note) / Σ(poids des critères notés) × 20.
//   Les critères non notés sont donc exclus et les poids restants renormalisés.
// - Score officiel d'un architecte : calculé à partir de la moyenne de chaque critère
//   sur les seuls membres « votant ».
// - Écarts types : calculés sur tous les membres du jury (votants + consultatifs).

/** @param array<int, float|int|null> $scores critère => note */
function weighted_total(array $scores): ?float
{
    $sum = 0.0;
    $weights = 0;
    foreach (criteria() as $n => $c) {
        if (isset($scores[$n])) {
            $sum += $c['weight'] * $scores[$n];
            $weights += $c['weight'];
        }
    }
    return $weights > 0 ? $sum / $weights * 20 : null;
}

function mean(array $values): ?float
{
    return $values ? array_sum($values) / count($values) : null;
}

/** Écart type de population ; nécessite au moins deux valeurs. */
function stddev(array $values): ?float
{
    $n = count($values);
    if ($n < 2) {
        return null;
    }
    $m = array_sum($values) / $n;
    $var = 0.0;
    foreach ($values as $v) {
        $var += ($v - $m) ** 2;
    }
    return sqrt($var / $n);
}

function round_or_null(?float $v, int $precision = 2): ?float
{
    return $v === null ? null : round($v, $precision);
}

/** Notes d'un membre pour un architecte : [critère => note]. */
function member_scores(int $memberId, int $architectId): array
{
    $rows = db_all('SELECT criterion, score FROM scores WHERE member_id = ? AND architect_id = ?', [$memberId, $architectId]);
    return array_map('intval', array_column($rows, 'score', 'criterion'));
}

function save_score(int $memberId, int $architectId, int $criterion, ?int $score): void
{
    if ($score === null) {
        db_exec('DELETE FROM scores WHERE member_id = ? AND architect_id = ? AND criterion = ?', [$memberId, $architectId, $criterion]);
        return;
    }
    db_exec(
        'INSERT INTO scores (member_id, architect_id, criterion, score) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE score = VALUES(score)',
        [$memberId, $architectId, $criterion, $score]
    );
}

/** Données du tableau de bord d'un architecte. */
function architect_dashboard(int $architectId): array
{
    $jury = get_jury();
    $raw = db_all('SELECT member_id, criterion, score FROM scores WHERE architect_id = ?', [$architectId]);
    $byMember = [];
    foreach ($raw as $r) {
        $byMember[$r['member_id']][(int) $r['criterion']] = (int) $r['score'];
    }

    $rows = [];
    $official = [];   // critère => notes des votants
    $all = [];        // critère => notes de tout le jury
    $totalsAll = [];  // scores totaux de chaque juré (pour l'écart type du total)
    foreach ($jury as $m) {
        $scores = $byMember[$m['id']] ?? [];
        $total = weighted_total($scores);
        foreach ($scores as $n => $s) {
            $all[$n][] = $s;
            if ($m['role'] === 'votant') {
                $official[$n][] = $s;
            }
        }
        if ($total !== null) {
            $totalsAll[] = $total;
        }
        $rows[] = [
            'id'       => (int) $m['id'],
            'name'     => $m['name'],
            'role'     => $m['role'],
            'scores'   => (object) $scores,
            'answered' => count($scores),
            'total'    => round_or_null($total, 1),
        ];
    }

    $summary = function (array $values) {
        $avg = [];
        foreach (criteria() as $n => $c) {
            $avg[$n] = isset($values[$n]) ? mean($values[$n]) : null;
        }
        return [
            'criteria' => array_map(fn ($v) => round_or_null($v), $avg),
            'total'    => round_or_null(weighted_total(array_filter($avg, fn ($v) => $v !== null)), 1),
        ];
    };

    $sd = [];
    foreach (criteria() as $n => $c) {
        $sd[$n] = round_or_null(isset($all[$n]) ? stddev($all[$n]) : null);
    }

    return [
        'rows'     => $rows,
        'official' => $summary($official),
        'all'      => $summary($all),
        'stddev'   => ['criteria' => $sd, 'total' => round_or_null(stddev($totalsAll), 1)],
        'voters'   => count(array_filter($jury, fn ($m) => $m['role'] === 'votant')),
    ];
}

/**
 * Classement final : architectes triés par score décroissant.
 * Score officiel calculé sur les votants ; $withConsultative ajoute les membres consultatifs.
 */
function ranking(bool $withConsultative = false): array
{
    $roles = $withConsultative ? "'votant', 'consultatif'" : "'votant'";
    $stats = db_all(
        "SELECT s.architect_id, s.criterion, AVG(s.score) AS avg_score
         FROM scores s JOIN members m ON m.id = s.member_id
         WHERE m.role IN ({$roles})
         GROUP BY s.architect_id, s.criterion"
    );
    $avg = [];
    foreach ($stats as $r) {
        $avg[$r['architect_id']][(int) $r['criterion']] = (float) $r['avg_score'];
    }
    // Nombre de membres pris en compte ayant noté les 6 critères
    $complete = db_all(
        "SELECT s.architect_id, COUNT(*) AS n FROM (
            SELECT s.architect_id, s.member_id FROM scores s JOIN members m ON m.id = s.member_id
            WHERE m.role IN ({$roles}) GROUP BY s.architect_id, s.member_id HAVING COUNT(*) = ?
         ) s GROUP BY s.architect_id",
        [count(criteria())]
    );
    $complete = array_column($complete, 'n', 'architect_id');

    $list = [];
    foreach (get_architects() as $a) {
        $crit = [];
        foreach (criteria() as $n => $c) {
            $crit[$n] = round_or_null($avg[$a['id']][$n] ?? null);
        }
        $list[] = [
            'id'       => (int) $a['id'],
            'agency'   => $a['agency'],
            'city'     => $a['city'],
            'criteria' => $crit,
            'total'    => round_or_null(weighted_total($avg[$a['id']] ?? []), 1),
            'complete' => (int) ($complete[$a['id']] ?? 0),
        ];
    }
    usort($list, function ($x, $y) {
        if ($x['total'] === $y['total']) {
            return strcmp($x['agency'], $y['agency']);
        }
        if ($x['total'] === null) return 1;
        if ($y['total'] === null) return -1;
        return $y['total'] <=> $x['total'];
    });
    return $list;
}

/** Nombre de membres pris en compte dans le classement. */
function ranking_voters(bool $withConsultative = false): int
{
    $roles = $withConsultative ? ['votant', 'consultatif'] : ['votant'];
    return count(array_filter(get_jury(), fn ($m) => in_array($m['role'], $roles, true)));
}

/** Classement sous forme de tableau pour l'export Excel : en-tête puis une ligne par architecte. */
function ranking_table(bool $withConsultative = false): array
{
    $voters = ranking_voters($withConsultative);
    $header = ['Rang', 'Agence', 'Ville', 'Score / 100'];
    foreach (criteria() as $n => $c) {
        $header[] = "C{$n} {$c['short']} ({$c['weight']} %)";
    }
    $header[] = "Votes complets (sur {$voters})";

    // Score = formule Excel sur les colonnes des critères (E…), mêmes règles que weighted_total() :
    // moyenne pondérée des critères renseignés, ramenée sur 100.
    $weights = '{' . implode(',', array_column(criteria(), 'weight')) . '}';
    $range = fn (int $row) => 'E' . $row . ':' . xlsx_col(3 + count(criteria())) . $row;

    $rows = [$header];
    $rank = 0;
    foreach (ranking($withConsultative) as $r) {
        $row = count($rows) + 1;
        $score = $r['total'] === null ? null : [
            'f' => "SUMPRODUCT({$weights},{$range($row)})/SUMPRODUCT({$weights},--({$range($row)}<>\"\"))*20",
            'v' => weighted_total(array_filter($r['criteria'], fn ($v) => $v !== null)),
        ];
        $rows[] = [$r['total'] === null ? '–' : ++$rank, $r['agency'], $r['city'], $score, ...array_values($r['criteria']), $r['complete']];
    }
    return $rows;
}

/** Notes d'un membre pour tous les dossiers : [architect_id => [critère => note]]. */
function member_progress(int $memberId): array
{
    $progress = [];
    foreach (db_all('SELECT architect_id, criterion, score FROM scores WHERE member_id = ?', [$memberId]) as $r) {
        $progress[(int) $r['architect_id']][(int) $r['criterion']] = (int) $r['score'];
    }
    return $progress;
}

/** Vrai si le membre a noté tous les critères de tous les dossiers proposés au jury. */
function has_completed_all(int $memberId): bool
{
    $architects = get_evaluable_architects();
    if (!$architects) {
        return false;
    }
    $progress = member_progress($memberId);
    $nbCriteria = count(criteria());
    foreach ($architects as $a) {
        if (count($progress[(int) $a['id']] ?? []) < $nbCriteria) {
            return false;
        }
    }
    return true;
}
