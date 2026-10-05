<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$method = $_SERVER['REQUEST_METHOD'];
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';

if ($method === 'POST') {
    check_csrf();
}

// ---------------------------------------------------------------- Connexion

if ($path === '/connexion') {
    if ($method === 'POST') {
        $email = mb_strtolower(trim($_POST['email'] ?? ''));
        $member = db_one('SELECT * FROM members WHERE LOWER(email) = ?', [$email]);
        if (!$member) {
            error_log("Demande de connexion pour une adresse inconnue : {$email}");
            render('login', ['sent' => false, 'email' => $email,
                'error' => 'Cette adresse ne fait pas partie du jury. Vérifiez-la, ou contactez un administrateur.']);
            exit;
        }
        $link = create_login_link((int) $member['id'], config('email_link_ttl_hours') * 3600);
        if (!send_login_email($member, $link)) {
            error_log("Lien de connexion non envoyé à {$email} (voir logs/mail.log)");
            render('login', ['sent' => false, 'email' => $email,
                'error' => 'L’e-mail n’a pas pu être envoyé. Réessayez plus tard, ou contactez un administrateur.']);
            exit;
        }
        render('login', ['sent' => true, 'email' => $email]);
        exit;
    }
    if (current_member()) {
        redirect('/');
    }
    render('login', ['sent' => false, 'email' => '']);
    exit;
}

if (preg_match('~^/auth/([a-f0-9]{64})$~', $path, $m)) {
    if (login_with_token($m[1])) {
        redirect('/');
    }
    render('message', [
        'title'   => 'Lien expiré',
        'message' => 'Ce lien de connexion n’est plus valide. Demandez-en un nouveau.',
        'action'  => ['/connexion', 'Recevoir un nouveau lien'],
    ]);
    exit;
}

if ($path === '/deconnexion' && $method === 'POST') {
    logout();
    redirect('/connexion');
}

// ---------------------------------------------------------------- Pages du jury

$me = require_login();
$canVote = in_array($me['role'], ['votant', 'consultatif'], true);
$withConsultative = !empty($_GET['consultatifs']); // classement : inclure les membres consultatifs

if ($path === '/') {
    render('home', [
        'me'         => $me,
        'architects' => get_evaluable_architects(),
        'progress'   => member_progress((int) $me['id']),
        'canVote'    => $canVote,
    ]);
    exit;
}

if ($path === '/classement') {
    require_results_access();
    render('ranking', ['me' => $me, 'ranking' => ranking($withConsultative), 'withConsultative' => $withConsultative]);
    exit;
}

if ($path === '/vue-par-personne') {
    require_results_access();
    render('persons', ['me' => $me, 'data' => person_view()]);
    exit;
}

if ($path === '/classement.xlsx') {
    require_results_access();
    $table = ranking_table($withConsultative);
    xlsx_download('classement-' . date('Y-m-d') . ($withConsultative ? '-avec-consultatifs' : '') . '.xlsx', 'Notation', $table,
        [7, 34, 18, 12, ...array_fill(0, count(criteria()), 18), 20]);
}

if (preg_match('~^/architecte/(\d+)(/tableau)?$~', $path, $m)) {
    $architect = get_architect((int) $m[1]) ?? not_found();
    if (!empty($m[2])) {
        require_results_access();
        render('dashboard', ['me' => $me, 'architect' => $architect, 'data' => architect_dashboard((int) $architect['id'])]);
    } else {
        $ids = array_column(get_evaluable_architects(), 'id');
        $pos = array_search($architect['id'], $ids); // false : dossier hors liste (sans lien Drive)
        render('architect', [
            'me'        => $me,
            'architect' => $architect,
            'scores'    => member_scores((int) $me['id'], (int) $architect['id']),
            'canVote'   => $canVote,
            'prevId'    => $pos === false ? null : ($ids[$pos - 1] ?? null),
            'nextId'    => $pos === false ? null : ($ids[$pos + 1] ?? null),
            'position'  => $pos === false ? null : $pos + 1,
            'count'     => count($ids),
        ]);
    }
    exit;
}

// ---------------------------------------------------------------- API JSON

if ($path === '/api/score' && $method === 'POST') {
    if (!$canVote) {
        json_response(['error' => 'Vous n’êtes pas membre du jury.'], 403);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $architectId = (int) ($input['architect_id'] ?? 0);
    $criterion = (int) ($input['criterion'] ?? 0);
    $score = $input['score'] ?? null;
    if (!get_architect($architectId) || !isset(criteria()[$criterion])
        || ($score !== null && (!is_int($score) || $score < 0 || $score > 5))) {
        json_response(['error' => 'Requête invalide'], 400);
    }
    $wasComplete = has_completed_all((int) $me['id']);
    save_score((int) $me['id'], $architectId, $criterion, $score);
    json_response([
        'ok'        => true,
        'total'     => round_or_null(weighted_total(member_scores((int) $me['id'], $architectId)), 1),
        // Vrai uniquement pour la note qui termine la notation de tous les dossiers
        'completed' => !$wasComplete && has_completed_all((int) $me['id']),
    ]);
}

if (preg_match('~^/api/architecte/(\d+)/tableau$~', $path, $m)) {
    require_results_access();
    get_architect((int) $m[1]) ?? json_response(['error' => 'Introuvable'], 404);
    json_response(architect_dashboard((int) $m[1]));
}

if ($path === '/api/vue-par-personne') {
    require_results_access();
    json_response(person_view());
}

if ($path === '/api/classement') {
    require_results_access();
    json_response(ranking($withConsultative));
}

// ---------------------------------------------------------------- Réglages (admin)

if (str_starts_with($path, '/reglages')) {
    require_admin();
    require APP_ROOT . '/src/settings.php';
    exit;
}

not_found();
