<?php
$pageTitle = $architect['agency'];
$driveUrl = architect_drive_url($architect);
$isMod = $me['role'] === 'mod';
// Étape 2 : les liens de navigation mènent directement au critère en cours de notation
$hash = $unlocked ? '#c' . $unlocked['criterion'] : '';
?>
<nav class="crumbs">
    <a href="/">← Tous les dossiers</a>
    <span class="pager">
        <?php if ($prevId): ?><a href="/architecte/<?= $prevId ?><?= $hash ?>" data-hash-link title="Dossier précédent">‹ Précédent</a><?php endif; ?>
        <?php if ($position): ?><span class="muted">Dossier <?= $position ?> / <?= $count ?></span><?php endif; ?>
        <?php if ($nextId): ?><a href="/architecte/<?= $nextId ?><?= $hash ?>" data-hash-link title="Dossier suivant">Suivant ›</a><?php endif; ?>
    </span>
</nav>

<section class="card architect-header">
    <div>
        <h1><?= e($architect['agency']) ?></h1>
        <dl class="facts">
            <?php if ($architect['referent']): ?><div><dt>Référent</dt><dd><?= e($architect['referent']) ?></dd></div><?php endif; ?>
            <?php if ($architect['city']): ?><div><dt>Ville</dt><dd><?= e($architect['city']) ?></dd></div><?php endif; ?>
            <?php if ($architect['website']): ?><div><dt>Site web</dt><dd><a href="<?= e($architect['website']) ?>" target="_blank" rel="noopener"><?= e(preg_replace('~^https?://(www\.)?~', '', rtrim($architect['website'], '/'))) ?></a></dd></div><?php endif; ?>
        </dl>
    </div>
    <div class="header-actions">
        <?php if ($driveUrl): ?>
            <a class="btn primary big" href="<?= e($driveUrl) ?>" target="_blank" rel="noopener"><span class="ico-drive"><svg viewBox="0 0 87.3 78" aria-hidden="true"><path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8h-27.5c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/><path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44a9.06 9.06 0 0 0-1.2 4.5h27.5z" fill="#00ac47"/><path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/><path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/><path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/><path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 28h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/></svg></span> <?= stage() === 1 ? 'Dossier de candidature' : 'Livrable étape 2' ?></a>
        <?php else: ?>
            <span class="btn big disabled" title="Lien à renseigner dans les réglages"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5A1.5 1.5 0 0 1 4.5 5h4.6l2 2.2h8.4A1.5 1.5 0 0 1 21 8.7v9.8a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5z" fill="currentColor"/></svg> Dossier non disponible</span>
        <?php endif; ?>
        <?php if (can_view_results($me)): ?><a class="btn" href="/architecte/<?= $architect['id'] ?>/tableau">Tableau de bord</a><?php endif; ?>
    </div>
</section>

<?php if (!$canVote): ?>
    <div class="flash">Vous n’êtes pas membre du jury : la grille est affichée en lecture seule.</div>
<?php elseif ($isMod): ?>
    <div class="flash">En tant que MOD, vous notez tous les critères : vos notes des critères <?= implode(' et ', array_keys(mod_criteria())) ?> sont reprises pour tous les membres du jury ;
        les autres comptent comme celles d’un membre consultatif.</div>
<?php elseif (stage() === 2): ?>
    <div class="flash">Conseil : notez chaque critère sur tous les dossiers avant de passer au suivant ; le critère en cours est mis en avant.
        Les critères <?= implode(' et ', array_keys(mod_criteria())) ?> sont notés par le MOD.</div>
<?php endif; ?>

<?php $illustration = architect_illustration((int) $architect['id']); ?>
<?php if ($me['is_admin']): // hors du formulaire de notation (formulaires imbriqués interdits), le champ y est rattaché par form="" ?>
    <form id="illustration-form" method="post" action="/architecte/<?= $architect['id'] ?>/illustration" enctype="multipart/form-data" hidden><?= csrf_field() ?></form>
<?php endif; ?>

<form class="scoring" id="scoring" data-architect="<?= $architect['id'] ?>" onsubmit="return false">
    <div class="scoring-summary">
        <div class="summary-illustration<?= $illustration ? '' : ' empty' ?>">
            <?php if ($illustration): ?>
                <img src="/architecte/<?= $architect['id'] ?>/illustration?v=<?= filemtime($illustration) ?>" alt="Illustration du projet <?= e($architect['agency']) ?>" title="Cliquer pour agrandir<?= $me['is_admin'] ? ' ; glisser-déposer une image pour la remplacer' : '' ?>">
            <?php endif; ?>
            <?php if ($me['is_admin'] && $illustration): // remplacement par glisser-déposer uniquement ?>
                <input form="illustration-form" type="file" name="illustration" accept="image/jpeg,image/png" hidden>
            <?php elseif ($me['is_admin']): ?>
                <label class="illustration-upload" title="Image JPG ou PNG, à choisir ou à glisser-déposer ici">
                    <input form="illustration-form" type="file" name="illustration" accept="image/jpeg,image/png" data-autosubmit>
                    <span>＋ Charger une illustration<small>ou glisser-déposer une image</small></span>
                </label>
            <?php elseif (!$illustration): ?>
                <span class="muted">Pas d’illustration</span>
            <?php endif; ?>
        </div>
        <div class="summary-who" aria-hidden="true">
            <strong><?= e($architect['agency']) ?></strong>
            <?php if ($architect['referent']): ?><span class="muted"><?= e($architect['referent']) ?></span><?php endif; ?>
        </div>
        <div class="summary-score">
            <span class="muted">Votre score pondéré</span>
            <strong id="my-total">—</strong><span class="muted">/ 100</span>
        </div>
        <span class="save-state" id="save-state" aria-live="polite"></span>
    </div>

    <?php foreach (criteria() as $n => $c):
        $current = $scores[$n] ?? null;
        $byMod = !empty($c['mod']);
        $own = $canVote && isset(own_criteria($me['role'])[$n]);
        $tracked = $own && !$byMod && stage() === 2; // étape 2 : critère en cours mis en avant
        $inProgress = $tracked && $unlocked && $n === $unlocked['criterion']; ?>
        <fieldset class="criterion<?= $byMod ? ' by-mod' : '' ?><?= $inProgress ? ' current' : '' ?>"
                  id="c<?= $n ?>" data-criterion="<?= $n ?>"<?= $tracked ? ' data-tracked' : '' ?> <?= $own ? '' : 'disabled' ?>>
            <legend>
                <span class="crit-num">Critère <?= $n ?></span>
                <span class="crit-title"><?= e($c['title']) ?></span>
                <span class="weight"><?= $c['weight'] ?> %</span>
                <?php if ($byMod): ?><span class="tag mod">Noté par le MOD</span><?php endif; ?>
            </legend>
            <?php if ($tracked): ?>
                <p class="current-note"<?= $inProgress ? '' : ' hidden' ?>><?= $inProgress ? "Critère en cours : {$unlocked['done']} / {$unlocked['count']} dossiers notés." : '' ?></p>
            <?php endif; ?>
            <?php if (!empty($c['note'])): ?>
                <p class="crit-note">⚠️ <?= e($c['note']) ?></p>
            <?php endif; ?>

            <div class="likert" role="radiogroup" aria-label="Note du critère <?= $n ?>">
                <label class="opt opt-na">
                    <input type="radio" name="c<?= $n ?>" value="" <?= $current === null ? 'checked' : '' ?>>
                    <span>Non évalué</span>
                </label>
                <?php foreach (score_scale() as $s => [$label, $desc]): ?>
                    <label class="opt opt-<?= $s ?>" title="<?= $s ?> — <?= e($label) ?> : <?= e($desc) ?>">
                        <input type="radio" name="c<?= $n ?>" value="<?= $s ?>" <?= $current === $s ? 'checked' : '' ?>>
                        <span><?= $s ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <?php if (isset($c['low'])): ?>
                <div class="anchors">
                    <p class="hint-low"><?= e($c['low']) ?></p>
                    <p class="hint-high"><?= e($c['high']) ?></p>
                </div>
            <?php else: ?>
                <div class="scale-labels" aria-hidden="true">
                    <?php foreach (score_scale() as $s => [$label]): ?><span><?= e($label) ?></span><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($c['points'])): ?>
                <ul class="crit-points">
                    <?php foreach ($c['points'] as $point): ?><li><?= e($point) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($own && stage() === 2): ?>
                <label class="comment">Commentaire <small class="muted">— visible de vous seul, pour vous souvenir de la raison de votre note</small>
                    <textarea name="comment<?= $n ?>" rows="2" maxlength="5000"><?= e($comments[$n] ?? '') ?></textarea>
                </label>
            <?php elseif ($byMod && !empty($modComments[$n])): ?>
                <div class="mod-comment"><strong>Commentaire du MOD</strong><p><?= nl2br(e($modComments[$n])) ?></p></div>
            <?php endif; ?>
            <?php if ($own && ($prevId || $nextId)): // affichés une fois le critère noté, vers le même critère des dossiers voisins ?>
                <nav class="crit-nav">
                    <?php if ($prevId): ?><a class="btn small" href="/architecte/<?= $prevId ?>#c<?= $n ?>">‹ Dossier précédent</a><?php endif; ?>
                    <?php if ($nextId): ?><a class="btn small primary" href="/architecte/<?= $nextId ?>#c<?= $n ?>">Dossier suivant ›</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </fieldset>
    <?php endforeach; ?>

    <?php if ($nextId): ?>
        <p class="next-link"><a class="btn primary" href="/architecte/<?= $nextId ?><?= $hash ?>" data-hash-link>Dossier suivant ›</a></p>
    <?php endif; ?>
</form>
<script type="application/json" id="weights"><?= json_encode(array_map(fn ($c) => $c['weight'], criteria())) ?></script>
