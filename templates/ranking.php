<?php
$pageTitle = 'Classement';
$voters = ranking_voters($withConsultative);
$query = $withConsultative ? '?consultatifs=1' : '';
?>
<section class="section">
    <div class="section-head">
        <h1>Classement <a class="btn small" href="/classement.xlsx<?= $query ?>" download>Exporter en Excel</a></h1>
        <p class="muted">Score pondéré sur 100, calculé sur les <?= $voters ?> membres
            <?= $withConsultative ? 'votants et consultatifs' : 'votants (hors consultatifs)' ?> ayant noté tous les dossiers. Mise à jour automatique.</p>
        <form method="get" action="/classement" class="consult-toggle">
            <label><input type="checkbox" name="consultatifs" value="1" data-autosubmit<?= $withConsultative ? ' checked' : '' ?>>
                Inclure les membres consultatifs</label>
            <noscript><button class="btn small">Appliquer</button></noscript>
        </form>
    </div>
    <div class="table-wrap">
        <table class="grid ranking" id="ranking"
               data-src="/api/classement<?= $query ?>" data-voters="<?= $voters ?>"
               data-criteria='<?= e(json_encode(array_map(fn ($c) => ['short' => $c['short'], 'title' => $c['title'], 'weight' => $c['weight']], criteria()), JSON_UNESCAPED_UNICODE)) ?>'>
            <thead></thead>
            <tbody></tbody>
        </table>
    </div>
    <script type="application/json" id="ranking-data"><?= json_encode($ranking, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
</section>
