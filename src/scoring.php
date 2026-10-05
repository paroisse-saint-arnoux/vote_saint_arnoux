<?php
// Calcul des scores.
//
// - Chaque critère est noté de 0 à 5 ; un critère « Non évalué » est simplement absent.
// - Score pondéré sur 100 = Σ(poids × note) / Σ(poids des critères notés) × 20.
//   Les critères non notés sont donc exclus et les poids restants renormalisés.
// - Score officiel d'un architecte : calculé à partir de la moyenne de chaque critère
//   sur les seuls membres « votant » (dans le classement : ceux ayant noté tous les dossiers).
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
    $consult = [];    // critère => notes des consultatifs
    $totalsAll = [];  // scores totaux de chaque juré (pour l'écart type du total)
    foreach ($jury as $m) {
        $scores = $byMember[$m['id']] ?? [];
        $total = weighted_total($scores);
        foreach ($scores as $n => $s) {
            $all[$n][] = $s;
            if ($m['role'] === 'votant') {
                $official[$n][] = $s;
            } else {
                $consult[$n][] = $s;
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
        'consultative' => $summary($consult),
        'stddev'   => ['criteria' => $sd, 'total' => round_or_null(stddev($totalsAll), 1)],
        'voters'   => count(array_filter($jury, fn ($m) => $m['role'] === 'votant')),
    ];
}

/** Ids des membres ayant noté tous les critères de tous les dossiers proposés au jury. */
function completed_member_ids(): array
{
    $architects = get_evaluable_architects();
    if (!$architects) {
        return [];
    }
    $rows = db_all(
        "SELECT s.member_id FROM scores s JOIN architects a ON a.id = s.architect_id
         WHERE a.drive_url <> '' AND s.criterion IN (" . implode(',', array_keys(criteria())) . ")
         GROUP BY s.member_id HAVING COUNT(*) = ?",
        [count($architects) * count(criteria())]
    );
    return array_map('intval', array_column($rows, 'member_id'));
}

/**
 * Membres pris en compte dans le classement : votants (et consultatifs si demandé)
 * ayant noté tous les dossiers.
 */
function ranking_members(bool $withConsultative = false): array
{
    $roles = $withConsultative ? ['votant', 'consultatif'] : ['votant'];
    $completed = completed_member_ids();
    return array_values(array_filter(get_jury(), fn ($m) => in_array($m['role'], $roles, true)
        && in_array((int) $m['id'], $completed, true)));
}

/** Moyenne de chaque critère sur les membres donnés : [architect_id => [critère => moyenne]]. */
function average_scores(array $memberIds): array
{
    if (!$memberIds) {
        return [];
    }
    $avg = [];
    $rows = db_all(
        'SELECT architect_id, criterion, AVG(score) AS avg_score FROM scores
         WHERE member_id IN (' . implode(',', array_map('intval', $memberIds)) . ')
         GROUP BY architect_id, criterion'
    );
    foreach ($rows as $r) {
        $avg[$r['architect_id']][(int) $r['criterion']] = (float) $r['avg_score'];
    }
    return $avg;
}

/**
 * Classement final : architectes triés par score décroissant.
 * Score officiel calculé sur les votants ayant tout noté ; $withConsultative ajoute les consultatifs ayant tout noté.
 */
function ranking(bool $withConsultative = false): array
{
    $ids = array_column(ranking_members($withConsultative), 'id');
    $avg = average_scores($ids);
    // Nombre de membres pris en compte ayant noté les 6 critères
    $complete = !$ids ? [] : db_all(
        'SELECT architect_id, COUNT(*) AS n FROM (
            SELECT architect_id, member_id FROM scores
            WHERE member_id IN (' . implode(',', array_map('intval', $ids)) . ')
            GROUP BY architect_id, member_id HAVING COUNT(*) = ?
         ) s GROUP BY architect_id',
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
    return count(ranking_members($withConsultative));
}

/** Rang de chaque dossier d'après son score (ex aequo : même rang) ; null si non noté. */
function rank_by(array $totals): array
{
    $scored = array_filter($totals, fn ($t) => $t !== null);
    arsort($scored);
    $ranks = array_fill_keys(array_keys($totals), null);
    $i = 0;
    $rank = 0;
    $prev = null;
    foreach ($scored as $id => $t) {
        $i++;
        if ($t !== $prev) {
            $rank = $i;
            $prev = $t;
        }
        $ranks[$id] = $rank;
    }
    return $ranks;
}

/**
 * Vue par personne : rang de chaque dossier pour le jury votant et le jury consultatif
 * (membres ayant tout noté), et pour chaque membre du jury.
 */
function person_view(): array
{
    $architects = get_evaluable_architects();
    $jury = get_jury();

    $columns = [
        'v' => ['label' => 'Jury votant', 'role' => 'votant', 'group' => true,
                'avg' => average_scores(array_column(ranking_members(), 'id'))],
        'c' => ['label' => 'Jury consultatif', 'role' => 'consultatif', 'group' => true,
                'avg' => average_scores(array_column(array_filter(ranking_members(true), fn ($m) => $m['role'] === 'consultatif'), 'id'))],
    ];
    $byMember = [];
    foreach (db_all('SELECT member_id, architect_id, criterion, score FROM scores') as $r) {
        $byMember[$r['member_id']][$r['architect_id']][(int) $r['criterion']] = (int) $r['score'];
    }
    foreach ($jury as $m) {
        if (empty($byMember[$m['id']])) {
            continue; // membre n'ayant encore rien noté
        }
        $columns['m' . $m['id']] = ['label' => $m['name'], 'role' => $m['role'], 'group' => false,
                                    'avg' => $byMember[$m['id']] ?? []];
    }

    $cells = [];
    foreach ($columns as $key => $col) {
        $totals = [];
        foreach ($architects as $a) {
            $totals[$a['id']] = round_or_null(weighted_total($col['avg'][$a['id']] ?? []), 1);
        }
        foreach (rank_by($totals) as $id => $rank) {
            $crit = [];
            foreach (criteria() as $n => $c) {
                $crit[$n] = round_or_null($col['avg'][$id][$n] ?? null);
            }
            $cells[$id][$key] = ['rank' => $rank, 'score' => $totals[$id], 'criteria' => $crit];
        }
    }

    return [
        'criteria' => array_map(fn ($c) => $c['short'], criteria()),
        'columns' => array_map(fn ($key, $col) => ['key' => $key, 'label' => $col['label'], 'role' => $col['role'], 'group' => $col['group']],
                               array_keys($columns), $columns),
        'rows'    => array_map(fn ($a) => ['id' => (int) $a['id'], 'agency' => $a['agency'], 'city' => $a['city'],
                                           'cells' => $cells[$a['id']],
                                           // nombre de votants ayant ce dossier dans leur top 10
                                           'top10' => count(array_filter(array_keys($columns), fn ($key) => !$columns[$key]['group']
                                               && $columns[$key]['role'] === 'votant'
                                               && ($cells[$a['id']][$key]['rank'] ?? 11) <= 10))], $architects),
    ];
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
