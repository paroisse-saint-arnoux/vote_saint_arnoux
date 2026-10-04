<?php $me = $me ?? current_member(); $flash = flash(); ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(isset($pageTitle) ? $pageTitle . ' · ' : '') ?>Sélection des architectes</title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= filemtime(APP_ROOT . '/public/assets/style.css') ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/">
        <span class="brand-mark">SA</span>
        <span>Sélection des architectes<small>Jury — Sélection 1</small></span>
    </a>
    <?php if ($me): ?>
        <button type="button" class="menu-toggle" aria-expanded="false" aria-controls="topnav" aria-label="Menu">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
        <nav class="topnav" id="topnav">
            <a href="/">Accueil</a>
            <?php if (can_view_results($me)): ?><a href="/classement">Classement</a><?php endif; ?>
            <?php if ($me['is_admin']): ?><a href="/reglages">Réglages</a><?php endif; ?>
            <span class="who" title="<?= e($me['email']) ?>"><?= e($me['name']) ?>
                <em class="role role-<?= e($me['role']) ?>"><?= e(role_label($me['role'])) ?></em></span>
            <form method="post" action="/deconnexion"><?= csrf_field() ?><button class="linkish">Déconnexion</button></form>
        </nav>
    <?php endif; ?>
</header>
<main class="page">
    <?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>
    <?= $content ?>
</main>
<script src="/assets/app.js?v=<?= filemtime(APP_ROOT . '/public/assets/app.js') ?>"></script>
</body>
</html>
