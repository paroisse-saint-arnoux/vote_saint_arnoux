<?php
$pageTitle = 'Accueil';
$nbCriteria = count(criteria());

// Tri proposé une fois tous les dossiers notés ; les clés (ordre de notation) sont conservées pour la numérotation.
$sorts = ['notation' => 'Par ordre de notation', 'alpha' => 'Alphabétique', 'score' => 'Score'];
$canSort = $canVote && has_completed_all((int) $me['id']);
$sort = $canSort && isset($sorts[$_GET['tri'] ?? '']) ? $_GET['tri'] : 'notation';
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
            $done = count(array_filter($progress, fn ($s) => count($s) >= $nbCriteria)); ?>
            <p class="muted">Vous avez entièrement noté <strong><?= $done ?></strong> dossier(s) sur <?= count($architects) ?>.</p>
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
            $n = count($scores);
            $state = $n === 0 ? 'todo' : ($n >= $nbCriteria ? 'done' : 'partial'); ?>
            <li class="architect-item state-<?= $state ?>">
                <span class="num"><?= $i + 1 ?></span>
                <a class="info" href="/architecte/<?= $a['id'] ?>">
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
                    <?php if ($canVote): ?><a class="btn small primary" href="/architecte/<?= $a['id'] ?>">Noter</a><?php endif; ?>
                    <?php if (can_view_results($me)): ?><a class="btn small" href="/architecte/<?= $a['id'] ?>/tableau">Tableau de bord</a><?php endif; ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
