<?php $pageTitle = 'Réglages'; ?>
<h1>Réglages</h1>

<?php if ($notice): ?><div class="flash error"><?= e($notice) ?></div><?php endif; ?>

<?php if ($generatedLink): ?>
    <div class="card link-box">
        <h3>Lien de connexion personnel — <?= e($generatedLink['member']['name']) ?></h3>
        <p class="muted">Valable <?= (int) config('admin_link_ttl_days') ?> jours. À transmettre uniquement à cette personne : il donne accès à son compte.</p>
        <div class="copy-row">
            <input type="text" readonly value="<?= e($generatedLink['url']) ?>" id="generated-link">
            <button type="button" class="btn primary" data-copy="#generated-link">Copier</button>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================================ Étape -->
<section class="section" id="etape">
    <div class="section-head">
        <h2>Étape de la consultation</h2>
        <p class="muted">L’étape choisie s’applique à tous les membres : dossiers proposés, grille de notation, classement et tableaux de bord.
           Les notes de chaque étape sont conservées séparément.</p>
    </div>
    <div class="card stage-card">
        <form method="post" action="/reglages" class="stage-choice">
            <?= csrf_field() ?><input type="hidden" name="action" value="stage_save">
            <?php foreach ([1 => 'Sélection des candidatures, tous les dossiers', 2 => 'Livrables des candidats retenus'] as $s => $label): ?>
                <label class="stage-option<?= stage() === $s ? ' active' : '' ?>">
                    <input type="radio" name="stage" value="<?= $s ?>" data-autosubmit<?= stage() === $s ? ' checked' : '' ?>>
                    <span><strong>Étape <?= $s ?></strong><small class="muted"><?= e($label) ?></small></span>
                </label>
            <?php endforeach; ?>
            <noscript><button class="btn small primary">Appliquer</button></noscript>
        </form>
        <div class="stage-selection">
            <strong>Dossiers retenus pour l’étape 2 (<?= count($selected) ?>)</strong>
            <span class="muted">— les 10 premiers du classement officiel de l’étape 1, figés lors du passage à l’étape 2.</span>
            <ol>
                <?php foreach ($selected as $r): ?>
                    <li><span class="muted"><?= $r['rank'] ?>.</span> <?= e($r['agency']) ?> <small class="muted"><?= fmt($r['total']) ?> / 100</small></li>
                <?php endforeach; ?>
            </ol>
            <form method="post" action="/reglages" class="inline">
                <?= csrf_field() ?>
                <button class="btn small" name="action" value="stage2_reselect"
                        data-confirm="Recalculer la liste des dossiers retenus d’après le classement actuel de l’étape 1 ?">Recalculer la sélection</button>
            </form>
        </div>
    </div>
</section>

<!-- ============================================================ Jury -->
<section class="section" id="jury">
    <div class="section-head">
        <h2>Membres du jury</h2>
        <p class="muted">« Votant » : compte dans le score. « Consultatif » : note, mais ne compte que pour les écarts types.
           « MOD » : à l’étape 2, note seul les critères 1 et 2, sa note étant reprise pour tous les membres du jury (un seul MOD).
           « Hors jury » : accès en consultation (ex. administrateur).</p>
    </div>
    <div class="table-wrap">
        <table class="grid form-grid">
            <thead><tr><th>Nom</th><th>E-mail</th><th>Statut</th><th>Admin</th><th title="Sessions ouvertes / dossiers notés">Activité</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($members as $m): $f = 'm' . $m['id']; ?>
                <tr>
                    <td><input form="<?= $f ?>" name="name" value="<?= e($m['name']) ?>" required></td>
                    <td><input form="<?= $f ?>" name="email" type="email" value="<?= e($m['email']) ?>" required></td>
                    <td><select form="<?= $f ?>" name="role">
                        <?php foreach (['votant', 'consultatif', 'mod', 'aucun'] as $r): ?>
                            <option value="<?= $r ?>" <?= $m['role'] === $r ? 'selected' : '' ?>><?= role_label($r) ?></option>
                        <?php endforeach; ?>
                    </select></td>
                    <td class="center"><input form="<?= $f ?>" type="checkbox" name="is_admin" value="1" <?= $m['is_admin'] ? 'checked' : '' ?>></td>
                    <td class="muted nowrap"><?= (int) ($sessionsCount[$m['id']] ?? 0) ?> session(s) · <?= (int) ($scoresCount[$m['id']] ?? 0) ?> dossier(s)</td>
                    <td class="nowrap">
                        <form method="post" action="/reglages" id="<?= $f ?>" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <button class="btn small primary" name="action" value="member_save">Enregistrer</button>
                            <button class="btn small" name="action" value="member_link" formnovalidate title="Générer un lien de connexion à transmettre">Lien</button>
                            <button class="btn small" name="action" value="member_email" formnovalidate title="Envoyer un lien de connexion par e-mail">E-mail</button>
                            <button class="btn small" name="action" value="member_logout_all" formnovalidate data-confirm="Fermer toutes les sessions de <?= e($m['name']) ?> ?" title="Déconnecter tous ses appareils">Déconnecter</button>
                            <?php if ($m['id'] != $me['id']): ?>
                                <button class="btn small danger" name="action" value="member_delete" formnovalidate data-confirm="Supprimer <?= e($m['name']) ?> et toutes ses notes ?">Supprimer</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr class="new-row">
                <td><input form="m-new" name="name" placeholder="Nouveau membre" required></td>
                <td><input form="m-new" name="email" type="email" placeholder="adresse@exemple.fr" required></td>
                <td><select form="m-new" name="role">
                    <option value="votant">Votant</option><option value="consultatif">Consultatif</option><option value="mod">MOD</option><option value="aucun">Hors jury</option>
                </select></td>
                <td class="center"><input form="m-new" type="checkbox" name="is_admin" value="1"></td>
                <td></td>
                <td><form method="post" action="/reglages" id="m-new" class="inline"><?= csrf_field() ?>
                    <button class="btn small primary" name="action" value="member_save">Ajouter</button></form></td>
            </tr>
            </tbody>
        </table>
    </div>
</section>

<!-- ============================================================ Architectes -->
<?php
$checkSelect = function (string $form, array $a = [], string $prefix = '') {
    $html = '';
    foreach (architect_checks() as $col => $label) {
        $name = $prefix === '' ? $col : "{$prefix}[{$col}]";
        $html .= '<td><select form="' . $form . '" name="' . $name . '" class="check-select" title="' . e($label) . '">';
        foreach (['—', '⚠️ Incohérences', '⛔ Gros souci'] as $level => $text) {
            $html .= '<option value="' . $level . '"' . ((int) ($a[$col] ?? 0) === $level ? ' selected' : '') . '>' . $text . '</option>';
        }
        $html .= '</select></td>';
    }
    return $html;
};
?>
<section class="section" id="architectes">
    <div class="section-head">
        <h2>Architectes <small class="muted">(<?= count($architects) ?>)</small></h2>
        <p class="muted">Ordre d’affichage aléatoire, identique pour tous les membres.
           Groupement, capacité financière, assurances : ⚠️ incohérences ou soucis relevés dans le dossier,
           ⛔ le MOD n’est pas confiant sur ce dossier pour cet axe.</p>
        <form method="post" action="/reglages" class="inline">
            <?= csrf_field() ?>
            <button class="btn small" name="action" value="architects_shuffle"
                    data-confirm="Tirer un nouvel ordre aléatoire des architectes ? L’ordre changera pour tous les membres du jury (les notes sont conservées).">Nouvel ordre aléatoire</button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="grid form-grid">
            <thead><tr><th>#</th><th>Agence</th><th>Référent</th><th>Ville</th><th>Site web</th><th>Dossier Google Drive</th><th>Livrable étape 2</th><?php foreach (architect_checks() as $label): ?><th><?= e($label) ?></th><?php endforeach; ?><th>Actions</th></tr></thead>
            <tbody>
            <?php // Toutes les lignes existantes appartiennent au même formulaire : « Enregistrer » les enregistre toutes
            foreach ($architects as $i => $a): $f = 'a' . $a['id']; $n = 'a[' . $a['id'] . ']'; ?>
                <tr class="<?= $a['drive_url'] ? '' : 'missing-drive' ?>">
                    <td class="muted"><?= $i + 1 ?></td>
                    <td><input form="a-all" name="<?= $n ?>[agency]" value="<?= e($a['agency']) ?>" required></td>
                    <td><input form="a-all" name="<?= $n ?>[referent]" value="<?= e($a['referent']) ?>"></td>
                    <td><input form="a-all" name="<?= $n ?>[city]" value="<?= e($a['city']) ?>"></td>
                    <td><input form="a-all" name="<?= $n ?>[website]" value="<?= e($a['website']) ?>"></td>
                    <td><input form="a-all" name="<?= $n ?>[drive_url]" value="<?= e($a['drive_url']) ?>" placeholder="https://drive.google.com/…" class="wide"></td>
                    <td><input form="a-all" name="<?= $n ?>[drive_url_stage2]" value="<?= e($a['drive_url_stage2']) ?>" placeholder="https://drive.google.com/…" class="wide"></td>
                    <?= $checkSelect('a-all', $a, $n) ?>
                    <td class="nowrap">
                        <form method="post" action="/reglages" id="<?= $f ?>" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button form="a-all" class="btn small primary" name="action" value="architects_save" title="Enregistre toutes les lignes du tableau">Enregistrer</button>
                            <button class="btn small danger" name="action" value="architect_delete" formnovalidate data-confirm="Supprimer <?= e($a['agency']) ?> et toutes ses notes ?">Supprimer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr class="new-row">
                <td></td>
                <td><input form="a-new" name="agency" placeholder="Nouvelle agence" required></td>
                <td><input form="a-new" name="referent" placeholder="Référent"></td>
                <td><input form="a-new" name="city" placeholder="Ville"></td>
                <td><input form="a-new" name="website" placeholder="Site web"></td>
                <td><input form="a-new" name="drive_url" placeholder="https://drive.google.com/…" class="wide"></td>
                <td><input form="a-new" name="drive_url_stage2" placeholder="https://drive.google.com/…" class="wide"></td>
                <?= $checkSelect('a-new') ?>
                <td><form method="post" action="/reglages" id="a-new" class="inline"><?= csrf_field() ?>
                    <button class="btn small primary" name="action" value="architect_save">Ajouter</button></form></td>
            </tr>
            </tbody>
        </table>
        <form method="post" action="/reglages" id="a-all" hidden><?= csrf_field() ?></form>
    </div>

    <form method="post" action="/reglages" enctype="multipart/form-data" class="card import-box">
        <?= csrf_field() ?>
        <strong>Importer un fichier TSV</strong>
        <span class="muted">Colonnes : Nom, Prénom, Agence, Ville / implantation, Site internet, Drive. Les agences existantes ne sont pas dupliquées ; leurs champs vides sont complétés.</span>
        <input type="file" name="tsv" accept=".tsv,.txt,text/tab-separated-values" required>
        <button class="btn" name="action" value="import">Importer</button>
    </form>
</section>

<!-- ============================================================ Zone de danger -->
<section class="section danger-zone" id="danger">
    <div class="section-head">
        <h2>Zone de danger</h2>
        <p class="muted">Actions irréversibles : aucune sauvegarde n’est faite.</p>
    </div>
    <form method="post" action="/reglages" class="card danger-card">
        <?= csrf_field() ?>
        <div>
            <strong>Réinitialiser toutes les réponses de l’étape <?= stage() ?></strong>
            <p class="muted">Supprime les <?= $scoresTotal ?> note(s) et les commentaires saisis à l’étape <?= stage() ?> par l’ensemble du jury, pour tous les dossiers.
               Les notes de l’autre étape, les membres, les architectes et les sessions sont conservés.</p>
        </div>
        <label>Saisissez <code>EFFACER</code> pour confirmer
            <input name="confirm" autocomplete="off" required pattern="EFFACER" placeholder="EFFACER"
                   data-unlock="#reset-btn" <?= $scoresTotal ? '' : 'disabled' ?>>
        </label>
        <button class="btn danger-solid" id="reset-btn" name="action" value="scores_reset" disabled
                data-confirm="Effacer définitivement les <?= $scoresTotal ?> note(s) de l’étape <?= stage() ?> de tous les membres du jury ? Cette action est irréversible.">Effacer les réponses de l’étape <?= stage() ?></button>
    </form>
</section>
