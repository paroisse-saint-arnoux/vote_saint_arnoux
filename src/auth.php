<?php
// Connexion par lien magique + cookie de session persistant.

const SESSION_COOKIE = 'jury_session';

function current_member(): ?array
{
    static $member = false;
    if ($member !== false) {
        return $member;
    }
    $member = null;
    $token = $_COOKIE[SESSION_COOKIE] ?? '';
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $hash = hash('sha256', $token);
        $member = db_one(
            'SELECT m.* FROM sessions s JOIN members m ON m.id = s.member_id WHERE s.token_hash = ?',
            [$hash]
        );
        if ($member) {
            db_exec('UPDATE sessions SET last_seen = NOW() WHERE token_hash = ? AND last_seen < NOW() - INTERVAL 1 HOUR', [$hash]);
        }
    }
    return $member;
}

function require_login(): array
{
    $member = current_member();
    if (!$member) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_response(['error' => 'Non connecté'], 401);
        }
        redirect('/connexion');
    }
    return $member;
}

function require_admin(): array
{
    $member = require_login();
    if (!$member['is_admin']) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_response(['error' => 'Réservé aux administrateurs'], 403);
        }
        http_response_code(403);
        render('message', ['title' => 'Accès réservé', 'message' => 'Cette page est réservée aux administrateurs.']);
        exit;
    }
    return $member;
}

/** Accès au classement et aux tableaux de bord : administrateurs, et jurés ayant noté 100 % des dossiers. */
function can_view_results(array $member): bool
{
    static $cache = [];
    return $cache[$member['id']] ??= $member['is_admin']
        || (in_array($member['role'], ['votant', 'consultatif'], true) && has_completed_all((int) $member['id']));
}

function require_results_access(): array
{
    $member = require_login();
    if (!can_view_results($member)) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_response(['error' => 'Accessible une fois tous les dossiers notés'], 403);
        }
        http_response_code(403);
        render('message', ['title' => 'Accès réservé', 'message' => 'Le classement et les tableaux de bord sont accessibles une fois tous les dossiers entièrement notés.']);
        exit;
    }
    return $member;
}

/** Crée un jeton de connexion et renvoie l'URL du lien magique. */
function create_login_link(int $memberId, int $ttlSeconds): string
{
    $token = bin2hex(random_bytes(32));
    db_exec(
        'INSERT INTO login_tokens (token_hash, member_id, expires_at) VALUES (?, ?, ?)',
        [hash('sha256', $token), $memberId, date('Y-m-d H:i:s', time() + $ttlSeconds)]
    );
    db_exec('DELETE FROM login_tokens WHERE expires_at < NOW()');
    return url('/auth/' . $token);
}

/**
 * Consomme un lien magique. Le lien reste réutilisable jusqu'à expiration :
 * les antivirus de messagerie « cliquent » souvent les liens avant l'utilisateur.
 */
function login_with_token(string $token): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }
    $row = db_one('SELECT member_id FROM login_tokens WHERE token_hash = ? AND expires_at > NOW()', [hash('sha256', $token)]);
    if (!$row) {
        return false;
    }
    start_session((int) $row['member_id']);
    return true;
}

function start_session(int $memberId): void
{
    $token = bin2hex(random_bytes(32));
    db_exec('INSERT INTO sessions (token_hash, member_id) VALUES (?, ?)', [hash('sha256', $token), $memberId]);
    setcookie(SESSION_COOKIE, $token, [
        'expires'  => time() + 10 * 365 * 24 * 3600, // durée « illimitée »
        'path'     => '/',
        'httponly' => true,
        'secure'   => is_https(),
        'samesite' => 'Lax',
    ]);
}

function logout(): void
{
    $token = $_COOKIE[SESSION_COOKIE] ?? '';
    if ($token !== '') {
        db_exec('DELETE FROM sessions WHERE token_hash = ?', [hash('sha256', $token)]);
    }
    setcookie(SESSION_COOKIE, '', ['expires' => 1, 'path' => '/']);
}
