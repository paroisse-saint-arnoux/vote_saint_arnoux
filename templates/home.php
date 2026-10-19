<?php
$pageTitle = 'Accueil';
$own = own_criteria($me['role']);  // critères notés par ce membre (étape 2 : sans les critères du MOD, sauf pour lui)
$nbCriteria = count($own);
$unlocked = $canVote ? unlocked_criterion($me) : null;

// Tri proposé une fois tous les dossiers notés ; les clés (ordre de notation) sont conservées pour la numérotation.
$sorts = ['score' => 'Par score', 'notation' => 'Par ordre de notation', 'alpha' => 'Par ordre alphabétique'];
$canSort = $canVote && has_completed_all((int) $me['id']);
// Tant que la notation est en cours, l'ordre reste celui de notation (il ne doit pas bouger au fil des notes).
$sort = !$canSort ? 'notation' : (isset($sorts[$_GET['tri'] ?? '']) ? $_GET['tri'] : 'score');
if ($sort === 'alpha') {
    $collator = class_exists(Collator::class) ? new Collator('fr_FR') : null;
    uasort($architects, fn ($x, $y) => $collator
        ? $collator->compare($x['agency'], $y['agency'])
        : strnatcasecmp($x['agency'], $y['agency']));
} elseif ($sort === 'score') {
    $totals = [];
    foreach ($architects as $a) {
        $totals[$a['id']] = weighted_total($progress[$a['id']] ?? []) ?? -1;
    }
    uasort($architects, fn ($x, $y) => $totals[$y['id']] <=> $totals[$x['id']]);
}
?>
<section class="section">
    <div class="section-head<?= $canSort ? ' with-sort' : '' ?>">
        <h1>Dossiers à évaluer</h1>
        <?php if ($canVote):
            $done = count(array_filter($architects, fn ($a) => count(array_intersect_key($progress[$a['id']] ?? [], $own)) >= $nbCriteria)); ?>
            <p class="muted">Vous avez entièrement noté <strong><?= $done ?></strong> dossier(s) sur <?= count($architects) ?>.</p>
        <?php endif; ?>
        <?php if ($unlocked): $c = criteria()[$unlocked['criterion']]; ?>
            <p class="stage-banner">Critère en cours : <strong>critère <?= $unlocked['criterion'] ?> — <?= e($c['title']) ?></strong>,
                <?= $unlocked['done'] ?> dossier(s) noté(s) sur <?= $unlocked['count'] ?>.
                Conseil : notez chaque critère sur tous les dossiers avant de passer au suivant.</p>
        <?php endif; ?>
        <?php if ($canVote && $me['role'] === 'mod'): ?>
            <p class="stage-banner">En tant que MOD, vous notez tous les critères : vos notes des critères <?= implode(' et ', array_keys(mod_criteria())) ?>
                sont reprises pour tous les membres du jury ; les autres comptent comme celles d’un membre consultatif.</p>
        <?php endif; ?>
        <?php if ($canSort): ?>
            <form method="get" action="/" class="sort-form">
                <label>Trier
                    <select name="tri" data-autosubmit>
                        <?php foreach ($sorts as $k => $label): ?>
                            <option value="<?= $k ?>"<?= $k === $sort ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <noscript><button class="btn small">Trier</button></noscript>
            </form>
        <?php endif; ?>
    </div>
    <ol class="architect-list">
        <?php foreach ($architects as $i => $a):
            $scores = $progress[$a['id']] ?? [];
            $n = count(array_intersect_key($scores, $own));
            $state = $n === 0 ? 'todo' : ($n >= $nbCriteria ? 'done' : 'partial'); ?>
            <li class="architect-item state-<?= $state ?>">
                <span class="num"><?= $i + 1 ?></span>
                <a class="info" href="/architecte/<?= $a['id'] ?><?= $unlocked ? '#c' . $unlocked['criterion'] : '' ?>">
                    <strong><?= e($a['agency']) ?></strong>
                    <span class="muted"><?= e(trim($a['referent'] . ' · ' . $a['city'], ' ·')) ?></span>
                </a>
                <?php if ($canVote): ?>
                    <span class="progress" title="<?= $n ?> critère(s) noté(s) sur <?= $nbCriteria ?>">
                        <?php foreach (criteria() as $k => $c): ?><i<?= isset($scores[$k]) ? ' class="s' . $scores[$k] . '"' : '' ?> title="Critère <?= $k ?> : <?= isset($scores[$k]) ? $scores[$k] . ' / 5' : 'non évalué' ?>"></i><?php endforeach; ?>
                    </span>
                    <span class="my-score" title="Votre score pondéré sur 100"><?= $state === 'done' ? fmt(weighted_total($scores)) . '<small> / 100</small>' : '' ?></span>
                <?php endif; ?>
                <span class="actions">
                    <?php if ($canVote): ?><a class="btn small primary" href="/architecte/<?= $a['id'] ?><?= $unlocked ? '#c' . $unlocked['criterion'] : '' ?>">Noter</a><?php endif; ?>
                    <?php if (can_view_results($me)): ?><a class="btn small" href="/architecte/<?= $a['id'] ?>/tableau">Tableau de bord</a><?php endif; ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
