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

// ---------------------------------------------------------------- Mise à jour de la base
// Code déployé avant la migration du schéma : page de maintenance, d'où un administrateur applique les migrations.

if (($pending = pending_migrations()) && !str_starts_with($path, '/auth/')) {
    $member = current_member();
    $isAdmin = $member && $member['is_admin'];
    $error = null;
    if ($isAdmin && $method === 'POST' && ($_POST['action'] ?? '') === 'migrate') {
        try {
            $done = run_migrations();
            error_log("Migrations appliquées par {$member['name']} (#{$member['id']}) : " . implode(', ', $done));
            flash('Base de données mise à jour : ' . count($done) . ' migration(s) appliquée(s).');
            redirect('/');
        } catch (RuntimeException $ex) {
            error_log($ex->getMessage());
            $error = $ex->getMessage();
            $pending = pending_migrations();
        }
    }
    if (str_starts_with($path, '/api/')) {
        json_response(['error' => 'Mise à jour en cours, réessayez dans quelques minutes.'], 503);
    }
    http_response_code(503);
    require APP_ROOT . '/templates/maintenance.php'; // page autonome : le reste de l'application attend la migration
    exit;
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
$canVote = can_vote($me);
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
    xlsx_download('classement-etape' . stage() . '-' . date('Y-m-d') . ($withConsultative ? '-avec-consultatifs' : '') . '.xlsx', 'Notation étape ' . stage(), $table,
        [7, 34, 18, 12, ...array_fill(0, count(criteria()), 18), 20]);
}

// Illustration d'un dossier : affichée aux membres connectés, chargée ou remplacée par un administrateur
if (preg_match('~^/architecte/(\d+)/illustration$~', $path, $m)) {
    $architect = get_architect((int) $m[1]) ?? not_found();
    if ($method === 'POST') {
        require_admin();
        $error = save_architect_illustration((int) $architect['id'], $_FILES['illustration'] ?? null);
        flash($error ?? 'Illustration enregistrée.');
        redirect('/architecte/' . $architect['id']);
    }
    $file = architect_illustration((int) $architect['id']) ?? not_found();
    header('Content-Type: ' . (str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg'));
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, max-age=86400'); // l'URL change (?v=) quand l'image est remplacée
    readfile($file);
    exit;
}

if (preg_match('~^/architecte/(\d+)(/tableau)?$~', $path, $m)) {
    $architect = get_architect((int) $m[1]) ?? not_found();
    if (!empty($m[2])) {
        require_results_access();
        render('dashboard', ['me' => $me, 'architect' => $architect, 'data' => architect_dashboard((int) $architect['id'])]);
    } else {
        $ids = array_column(get_evaluable_architects(), 'id');
        $pos = array_search($architect['id'], $ids); // false : dossier hors liste (sans lien Drive, ou non retenu)
        // Étape 2 : chaque critère se note sur tous les dossiers, la navigation boucle donc du dernier au premier
        $loop = stage() === 2 && count($ids) > 1;
        $mod = get_mod();
        render('architect', [
            'me'          => $me,
            'architect'   => $architect,
            'scores'      => member_scores((int) $me['id'], (int) $architect['id']),
            'comments'    => member_comments((int) $me['id'], (int) $architect['id']),
            'modComments' => $mod && $me['role'] !== 'mod' ? member_comments((int) $mod['id'], (int) $architect['id']) : [],
            'canVote'     => $canVote && ($pos !== false || stage() === 1),
            'unlocked'    => unlocked_criterion($me),
            'prevId'      => $pos === false ? null : ($ids[$pos - 1] ?? ($loop ? end($ids) : null)),
            'nextId'      => $pos === false ? null : ($ids[$pos + 1] ?? ($loop ? $ids[0] : null)),
            'position'    => $pos === false ? null : $pos + 1,
            'count'       => count($ids),
        ]);
    }
    exit;
}

// ---------------------------------------------------------------- API JSON

// Vérifie qu'une note ou un commentaire peut être enregistré ; renvoie [architecte, critère].
$scoringInput = function (array $input) use ($me, $canVote): array {
    if (!$canVote) {
        json_response(['error' => 'Vous n’êtes pas membre du jury.'], 403);
    }
    $architectId = (int) ($input['architect_id'] ?? 0);
    $criterion = (int) ($input['criterion'] ?? 0);
    $evaluable = stage() === 1 ? (bool) get_architect($architectId)
        : in_array($architectId, array_map('intval', array_column(get_evaluable_architects(), 'id')), true);
    if (!$evaluable || !isset(criteria()[$criterion])) {
        json_response(['error' => 'Requête invalide'], 400);
    }
    if (!isset(own_criteria($me['role'])[$criterion])) {
        json_response(['error' => $me['role'] === 'mod' ? 'Ce critère est noté par les membres du jury.' : 'Ce critère est noté par le MOD.'], 403);
    }
    $unlocked = unlocked_criterion($me);
    if (!criterion_unlocked($unlocked, $criterion)) {
        json_response(['error' => "Notez d’abord le critère {$unlocked['criterion']} sur tous les dossiers."], 403);
    }
    return [$architectId, $criterion];
};

if ($path === '/api/score' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    [$architectId, $criterion] = $scoringInput($input);
    $score = $input['score'] ?? null;
    if ($score !== null && (!is_int($score) || $score < 0 || $score > 5)) {
        json_response(['error' => 'Requête invalide'], 400);
    }
    $wasComplete = has_completed_all((int) $me['id']);
    save_score((int) $me['id'], $architectId, $criterion, $score);
    json_response([
        'ok'        => true,
        'total'     => round_or_null(weighted_total(member_scores((int) $me['id'], $architectId)), 1),
        // Vrai uniquement pour la note qui termine la notation de tous les dossiers
        'completed' => !$wasComplete && has_completed_all((int) $me['id']),
        'unlocked'  => unlocked_criterion($me),
    ]);
}

if ($path === '/api/comment' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    [$architectId, $criterion] = $scoringInput($input);
    $comment = $input['comment'] ?? '';
    if (!is_string($comment) || mb_strlen($comment) > 5000) {
        json_response(['error' => 'Commentaire invalide (5 000 caractères au plus)'], 400);
    }
    save_comment((int) $me['id'], $architectId, $criterion, $comment);
    json_response(['ok' => true]);
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
