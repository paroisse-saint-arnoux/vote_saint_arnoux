<?php $pageTitle = $architect['agency']; ?>
<nav class="crumbs">
    <a href="/">← Tous les dossiers</a>
    <span class="pager">
        <?php if ($prevId): ?><a href="/architecte/<?= $prevId ?>" title="Dossier précédent">‹ Précédent</a><?php endif; ?>
        <?php if ($position): ?><span class="muted">Dossier <?= $position ?> / <?= $count ?></span><?php endif; ?>
        <?php if ($nextId): ?><a href="/architecte/<?= $nextId ?>" title="Dossier suivant">Suivant ›</a><?php endif; ?>
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
        <?php if ($architect['drive_url']): ?>
            <a class="btn primary big" href="<?= e($architect['drive_url']) ?>" target="_blank" rel="noopener"><span class="ico-drive"><svg viewBox="0 0 87.3 78" aria-hidden="true"><path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8h-27.5c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/><path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44a9.06 9.06 0 0 0-1.2 4.5h27.5z" fill="#00ac47"/><path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/><path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/><path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/><path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 28h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/></svg></span> Dossier de candidature</a>
        <?php else: ?>
            <span class="btn big disabled" title="Lien à renseigner dans les réglages"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5A1.5 1.5 0 0 1 4.5 5h4.6l2 2.2h8.4A1.5 1.5 0 0 1 21 8.7v9.8a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5z" fill="currentColor"/></svg> Dossier non disponible</span>
        <?php endif; ?>
        <?php if (can_view_results($me)): ?><a class="btn" href="/architecte/<?= $architect['id'] ?>/tableau">Tableau de bord</a><?php endif; ?>
    </div>
</section>

<?php if (!$canVote): ?>
    <div class="flash">Vous n’êtes pas membre du jury : la grille est affichée en lecture seule.</div>
<?php endif; ?>

<form class="scoring" id="scoring" data-architect="<?= $architect['id'] ?>" onsubmit="return false">
    <div class="scoring-summary">
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

    <?php foreach (criteria() as $n => $c): $current = $scores[$n] ?? null; ?>
        <fieldset class="criterion" data-criterion="<?= $n ?>" <?= $canVote ? '' : 'disabled' ?>>
            <legend>
                <span class="crit-num">Critère <?= $n ?></span>
                <span class="crit-title"><?= e($c['title']) ?></span>
                <span class="weight"><?= $c['weight'] ?> %</span>
            </legend>
            <?php if (!empty($c['note'])): ?>
                <p class="crit-note">⚠️ <?= e($c['note']) ?></p>
            <?php endif; ?>

            <div class="likert" role="radiogroup" aria-label="Note du critère <?= $n ?>">
                <label class="opt opt-na">
                    <input type="radio" name="c<?= $n ?>" value="" <?= $current === null ? 'checked' : '' ?>>
                    <span>Non évalué</span>
                </label>
                <?php for ($s = 0; $s <= 5; $s++): ?>
                    <label class="opt opt-<?= $s ?>">
                        <input type="radio" name="c<?= $n ?>" value="<?= $s ?>" <?= $current === $s ? 'checked' : '' ?>>
                        <span><?= $s ?></span>
                    </label>
                <?php endfor; ?>
            </div>

            <div class="anchors">
                <p class="hint-low"><?= e($c['low']) ?></p>
                <p class="hint-high"><?= e($c['high']) ?></p>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <?php if ($nextId): ?>
        <p class="next-link"><a class="btn primary" href="/architecte/<?= $nextId ?>">Dossier suivant ›</a></p>
    <?php endif; ?>
</form>
<script type="application/json" id="weights"><?= json_encode(array_map(fn ($c) => $c['weight'], criteria())) ?></script>
