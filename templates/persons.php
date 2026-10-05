<?php $pageTitle = 'Vue par personne'; ?>
<section class="section">
    <div class="section-head">
        <h1>Vue par personne</h1>
        <p class="muted">Rang de chaque dossier selon le jury votant et le jury consultatif
            (membres ayant noté tous les dossiers), et selon chaque membre du jury. Les 10 premiers sont en vert.
            Cliquez sur un en-tête pour trier. Mise à jour automatique.</p>
        <div class="consult-toggle">
            <label><input type="checkbox" id="persons-scores"> Afficher les scores sur 100 plutôt que le classement</label>
        </div>
    </div>
    <div class="table-wrap">
        <table class="grid persons" id="persons" data-src="/api/vue-par-personne">
            <thead></thead>
            <tbody></tbody>
        </table>
    </div>
    <script type="application/json" id="persons-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
</section>
