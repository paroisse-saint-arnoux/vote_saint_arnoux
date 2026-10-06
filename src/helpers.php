<?php

function config(string $key)
{
    return $GLOBALS['config'][$key] ?? null;
}

function criteria(): array
{
    return $GLOBALS['criteria'];
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
    return ['votant' => 'Votant', 'consultatif' => 'Consultatif', 'aucun' => 'Hors jury'][$role] ?? $role;
}

function fmt(?float $value, int $decimals = 1): string
{
    return $value === null ? '—' : number_format($value, $decimals, ',', ' ');
}
