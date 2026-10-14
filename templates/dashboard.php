<?php $pageTitle = 'Tableau de bord — ' . $architect['agency']; ?>
<nav class="crumbs">
    <a href="/classement">← Classement</a>
    <a href="/architecte/<?= $architect['id'] ?>">Grille de notation ›</a>
</nav>

<section class="section">
    <div class="section-head">
        <h1><?= e($architect['agency']) ?> <small class="muted">— tableau de bord</small></h1>
        <p class="muted">
            <?= e(trim($architect['referent'] . ' · ' . $architect['city'], ' ·')) ?>
            <?php if (architect_drive_url($architect)): ?> · <a href="<?= e(architect_drive_url($architect)) ?>" target="_blank" rel="noopener"><?= stage() === 1 ? 'Dossier de candidature' : 'Livrable étape 2' ?></a><?php endif; ?>
        </p>
    </div>

    <div class="kpis">
        <div class="kpi main"><span>Score officiel</span><strong id="kpi-total">—</strong><em>/ 100 · votants uniquement</em></div>
        <div class="kpi"><span>Avec consultatifs</span><strong id="kpi-all">—</strong><em>/ 100 · pour information</em></div>
        <div class="kpi"><span>Votants ayant tout noté</span><strong id="kpi-complete">—</strong><em id="kpi-complete-sub"></em></div>
    </div>

    <div class="table-wrap">
        <table class="grid dashboard" id="dashboard" data-src="/api/architecte/<?= $architect['id'] ?>/tableau"
               data-me="<?= (int) $me['id'] ?>"
               data-criteria='<?= e(json_encode(array_map(fn ($c) => ['short' => $c['short'], 'title' => $c['title'], 'weight' => $c['weight']], criteria()), JSON_UNESCAPED_UNICODE)) ?>'>
            <thead></thead>
            <tbody></tbody>
        </table>
    </div>
    <p class="legend muted">
        Écart type calculé sur l’ensemble du jury (votants et consultatifs) :
        <span class="sd-chip sd-0">&lt; 0,75 consensus</span>
        <span class="sd-chip sd-1">0,75 – 1,25 à surveiller</span>
        <span class="sd-chip sd-2">&gt; 1,25 à discuter</span>
        <br>Un score individuel marqué <em>partiel</em> est calculé sur les seuls critères notés (pondérations renormalisées).
        <span class="live" id="live">● en direct</span>
    </p>
    <script type="application/json" id="dashboard-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
</section>
