<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mise à jour · Sélection des architectes</title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= filemtime(APP_ROOT . '/public/assets/style.css') ?>">
</head>
<body>
<main class="page">
    <section class="card narrow">
        <h1>Mise à jour en cours</h1>
        <?php if (!$isAdmin): ?>
            <p>L’outil de notation est en cours de mise à jour. Réessayez dans quelques minutes.</p>
            <p class="muted">Vos notes sont conservées.</p>
        <?php else: ?>
            <p>Le code a été mis à jour : la base de données doit l’être aussi avant de rouvrir l’outil au jury.</p>
            <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
            <p><strong><?= count($pending) ?> migration(s) en attente :</strong></p>
            <ul class="migrations"><?php foreach (array_keys($pending) as $name): ?><li><code><?= e($name) ?></code></li><?php endforeach; ?></ul>
            <form method="post" action="/" class="stack">
                <?= csrf_field() ?>
                <button class="btn primary" name="action" value="migrate">Mettre à jour la base</button>
            </form>
            <p class="muted">Pensez à sauvegarder la base avant (export phpMyAdmin). Équivalent en ligne de commande :
                <code>php scripts/migrate.php</code></p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
