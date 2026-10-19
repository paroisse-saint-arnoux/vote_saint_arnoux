<?php

function config(string $key)
{
    return $GLOBALS['config'][$key] ?? null;
}

/** Critères de l'étape en cours. */
function criteria(): array
{
    return $GLOBALS['criteria'][stage()];
}

/** Critères notés par un membre : à l'étape 2, le MOD note tous les critères, les autres membres tous sauf les critères « mod ». */
function own_criteria(string $role): array
{
    if (stage() === 1 || $role === 'mod') {
        return criteria();
    }
    return array_filter(criteria(), fn ($c) => empty($c['mod']));
}

/** Critères notés par le MOD et repris pour tout le jury (étape 2 uniquement). */
function mod_criteria(): array
{
    return stage() === 1 ? [] : array_filter(criteria(), fn ($c) => !empty($c['mod']));
}

/** Échelle de notation commune à toutes les étapes : note => [libellé, description]. */
function score_scale(): array
{
    return [
        0 => ['Absent', 'Le critère n’est pas traité'],
        1 => ['Très insuffisant', 'Réponse formelle, sans contenu propre au site'],
        2 => ['Insuffisant', 'Réponse partielle ou générique'],
        3 => ['Acceptable', 'Répond, avec des insuffisances qui n’engagent pas le projet'],
        4 => ['Satisfaisant', 'Répond pleinement, sans apport particulier'],
        5 => ['Excellent', 'Répond pleinement et apporte une valeur pas formulée'],
    ];
}

function setting(string $name): ?string
{
    return db_one('SELECT value FROM settings WHERE name = ?', [$name])['value'] ?? null;
}

function save_setting(string $name, string $value): void
{
    db_exec('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, $value]);
}

/** Étape de la consultation affichée (réglage, étape 2 par défaut). */
function stage(): int
{
    return $GLOBALS['stage'] ??= setting('stage') === '1' ? 1 : 2;
}

/** Exécute $fn comme si l'étape $stage était sélectionnée. */
function with_stage(int $stage, callable $fn)
{
    $previous = stage();
    $GLOBALS['stage'] = $stage;
    try {
        return $fn();
    } finally {
        $GLOBALS['stage'] = $previous;
    }
}

/** Lien du dossier à évaluer à l'étape en cours : candidature (étape 1) ou livrable (étape 2). */
function architect_drive_url(array $architect): string
{
    return stage() === 1 ? $architect['drive_url'] : $architect['drive_url_stage2'];
}

/** Illustration d'un dossier (uploads/illustrations/{id}.jpg ou .png, hors racine web), ou null si absente. */
function architect_illustration(int $architectId): ?string
{
    foreach (['jpg', 'png'] as $ext) {
        $file = APP_ROOT . "/uploads/illustrations/$architectId.$ext";
        if (is_file($file)) {
            return $file;
        }
    }
    return null;
}

/** Enregistre l'illustration envoyée ($_FILES) ; renvoie un message d'erreur, ou null si tout va bien. */
function save_architect_illustration(int $architectId, ?array $upload): ?string
{
    if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return 'Aucun fichier reçu.';
    }
    if (in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $upload['size'] > 10 * 1024 * 1024) {
        return 'Image trop lourde (10 Mo au plus).';
    }
    if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        return 'L’envoi du fichier a échoué.';
    }
    $info = @getimagesize($upload['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$info['mime'] ?? ''] ?? null;
    if (!$ext) {
        return 'Format non reconnu : seules les images JPG et PNG sont acceptées.';
    }
    if ($info[0] * $info[1] > 50_000_000) {
        return 'Image trop grande (50 mégapixels au plus).';
    }
    $dir = APP_ROOT . '/uploads/illustrations';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException("Impossible de créer $dir");
    }
    if ($previous = architect_illustration($architectId)) {
        unlink($previous);
    }
    if (!resize_image($upload['tmp_name'], "$dir/$architectId.$ext", $ext)
        && !move_uploaded_file($upload['tmp_name'], "$dir/$architectId.$ext")) {
        throw new RuntimeException("Impossible d'enregistrer l'illustration du dossier $architectId");
    }
    return null;
}

/**
 * Réduit l'image à 800 px au plus sur son grand côté (redressée selon l'orientation EXIF des photos)
 * et l'enregistre dans $target. Renvoie false si GD n'est pas disponible : l'original est alors conservé.
 */
function resize_image(string $source, string $target, string $ext, int $maxSide = 800): bool
{
    if (!function_exists('imagecreatefromjpeg')) {
        error_log('Extension GD absente : illustration enregistrée sans redimensionnement');
        return false;
    }
    $img = $ext === 'jpg' ? imagecreatefromjpeg($source) : imagecreatefrompng($source);
    if (!$img) {
        throw new RuntimeException("Image illisible : $source");
    }
    if ($ext === 'jpg' && function_exists('exif_read_data')) {
        $angle = [3 => 180, 6 => -90, 8 => 90][@exif_read_data($source)['Orientation'] ?? 1] ?? 0;
        if ($angle) {
            $img = imagerotate($img, $angle, 0);
        }
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $ratio = min(1, $maxSide / max($w, $h));
    if ($ratio < 1) {
        $resized = imagecreatetruecolor(max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio)));
        if ($ext === 'png') { // transparence conservée
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        imagecopyresampled($resized, $img, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $w, $h);
        $img = $resized;
    }
    $ok = $ext === 'jpg' ? imagejpeg($img, $target, 82) : imagepng($img, $target, 9);
    if (!$ok) {
        throw new RuntimeException("Impossible d'enregistrer $target");
    }
    return true;
}

/** Nombre de dossiers mis en évidence dans la vue par personne (« top N »). */
function top_n(): int
{
    return stage() === 1 ? 10 : 3;
}

/**
 * Points de vigilance relevés par le MOD sur chaque dossier : colonne => libellé.
 * Valeurs : 0 = pas de souci, 1 = incohérences relevées (⚠️), 2 = gros souci (⛔).
 */
function architect_checks(): array
{
    return ['check_grouping' => 'Groupement', 'check_financial' => 'Finances', 'check_insurance' => 'Assurances'];
}

/** Niveaux des points de vigilance d'un architecte : [colonne => 0|1|2]. */
function architect_check_levels(array $architect): array
{
    $levels = [];
    foreach (architect_checks() as $col => $label) {
        $levels[$col] = (int) ($architect[$col] ?? 0);
    }
    return $levels;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    return rtrim(config('app_url'), '/') . $path;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function render(string $template, array $vars = []): void
{
    csrf_token(); // pose le cookie CSRF avant toute sortie HTML
    extract($vars);
    ob_start();
    require APP_ROOT . '/templates/' . $template . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/templates/layout.php';
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function not_found(): void
{
    http_response_code(404);
    render('message', ['title' => 'Page introuvable', 'message' => 'Cette page n’existe pas.']);
    exit;
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        setcookie('flash', $message, ['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        return null;
    }
    $msg = $_COOKIE['flash'] ?? null;
    if ($msg !== null) {
        setcookie('flash', '', ['expires' => 1, 'path' => '/']);
    }
    return $msg;
}

/** Ajoute https:// à une URL saisie sans schéma ; rejette tout autre schéma. */
function normalize_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (!preg_match('~^https?://~i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }
    return $value;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function csrf_token(): string
{
    if (empty($_COOKIE['csrf']) || !preg_match('/^[a-f0-9]{64}$/', $_COOKIE['csrf'])) {
        $token = bin2hex(random_bytes(32));
        setcookie('csrf', $token, ['path' => '/', 'httponly' => true, 'samesite' => 'Strict', 'secure' => is_https()]);
        $_COOKIE['csrf'] = $token;
    }
    return $_COOKIE['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_COOKIE['csrf']) || !hash_equals($_COOKIE['csrf'], (string) $sent)) {
        http_response_code(403);
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_response(['error' => 'Jeton de sécurité invalide, rechargez la page.'], 403);
        }
        render('message', ['title' => 'Session expirée', 'message' => 'Le formulaire a expiré. Rechargez la page et recommencez.']);
        exit;
    }
}

function role_label(string $role): string
{
    return ['votant' => 'Votant', 'consultatif' => 'Consultatif', 'mod' => 'MOD', 'aucun' => 'Hors jury'][$role] ?? $role;
}

function fmt(?float $value, int $decimals = 1): string
{
    return $value === null ? '—' : number_format($value, $decimals, ',', ' ');
}
