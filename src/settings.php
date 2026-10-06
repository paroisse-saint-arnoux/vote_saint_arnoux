<?php
// Page de réglage : architectes et membres du jury. Inclus depuis public/index.php (admin vérifié).

$notice = null;
$generatedLink = null;

$architectInput = function (array $src): array {
    $data = [
        'agency'    => trim($src['agency'] ?? ''),
        'referent'  => trim($src['referent'] ?? ''),
        'city'      => trim($src['city'] ?? ''),
        'website'   => normalize_url($src['website'] ?? ''),
        'drive_url' => normalize_url($src['drive_url'] ?? ''),
    ];
    foreach (architect_checks() as $col => $label) {
        $data[$col] = max(0, min(2, (int) ($src[$col] ?? 0)));
    }
    if ($data['agency'] === '') {
        throw new InvalidArgumentException('Le nom de l’agence est obligatoire.');
    }
    return $data;
};

$memberInput = function (): array {
    $role = $_POST['role'] ?? 'votant';
    return [
        'name'     => trim($_POST['name'] ?? ''),
        'email'    => mb_strtolower(trim($_POST['email'] ?? '')),
        'role'     => in_array($role, ['votant', 'consultatif', 'aucun'], true) ? $role : 'votant',
        'is_admin' => empty($_POST['is_admin']) ? 0 : 1,
    ];
};

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    try {
        switch ($action) {
            case 'architect_save':
                $data = $architectInput($_POST);
                create_architect($data);
                flash('Architecte « ' . $data['agency'] . ' » ajouté.');
                redirect('/reglages#architectes');

            case 'architects_save':
                $rows = array_map($architectInput, (array) ($_POST['a'] ?? [])); // tout est validé avant d'écrire
                $changed = 0;
                db()->beginTransaction();
                foreach ($rows as $rowId => $data) {
                    $set = implode(', ', array_map(fn ($col) => "{$col} = ?", array_keys($data)));
                    $changed += db_exec("UPDATE architects SET {$set} WHERE id = ?", [...array_values($data), (int) $rowId]);
                }
                db()->commit();
                flash(count($rows) . " architecte(s) enregistré(s), dont {$changed} modifié(s).");
                redirect('/reglages#architectes');

            case 'architect_delete':
                db_exec('DELETE FROM architects WHERE id = ?', [$id]);
                flash('Architecte supprimé, ainsi que ses notes.');
                redirect('/reglages#architectes');

            case 'architects_shuffle':
                $n = db_exec('UPDATE architects SET sort_key = FLOOR(RAND() * 2000000000)');
                flash("Nouvel ordre aléatoire tiré pour les {$n} architecte(s).");
                redirect('/reglages#architectes');

            case 'import':
                if (($_FILES['tsv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException('Aucun fichier reçu.');
                }
                $r = import_architects(parse_architects_tsv(file_get_contents($_FILES['tsv']['tmp_name'])));
                flash("Import terminé : {$r['created']} architecte(s) ajouté(s), {$r['updated']} complété(s).");
                redirect('/reglages#architectes');

            case 'member_save':
                $data = $memberInput();
                if ($data['name'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Nom et adresse e-mail valide obligatoires.');
                }
                if ($id === (int) $me['id'] && !$data['is_admin']) {
                    throw new InvalidArgumentException('Vous ne pouvez pas retirer vos propres droits d’administration.');
                }
                if ($id) {
                    db_exec('UPDATE members SET name = ?, email = ?, role = ?, is_admin = ? WHERE id = ?', [...array_values($data), $id]);
                    flash('Membre « ' . $data['name'] . ' » enregistré.');
                } else {
                    $pos = (int) db()->query('SELECT COALESCE(MAX(position), 0) + 1 FROM members')->fetchColumn();
                    db_exec('INSERT INTO members (name, email, role, is_admin, position) VALUES (?, ?, ?, ?, ?)', [...array_values($data), $pos]);
                    flash('Membre « ' . $data['name'] . ' » ajouté.');
                }
                redirect('/reglages#jury');

            case 'member_delete':
                if ($id === (int) $me['id']) {
                    throw new InvalidArgumentException('Vous ne pouvez pas vous supprimer vous-même.');
                }
                db_exec('DELETE FROM members WHERE id = ?', [$id]);
                flash('Membre supprimé, ainsi que ses notes.');
                redirect('/reglages#jury');

            case 'member_link':
                $member = db_one('SELECT * FROM members WHERE id = ?', [$id]) ?? throw new InvalidArgumentException('Membre introuvable.');
                $generatedLink = [
                    'member' => $member,
                    'url'    => create_login_link($id, config('admin_link_ttl_days') * 86400),
                ];
                break;

            case 'member_email':
                $member = db_one('SELECT * FROM members WHERE id = ?', [$id]) ?? throw new InvalidArgumentException('Membre introuvable.');
                $ok = send_login_email($member, create_login_link($id, config('email_link_ttl_hours') * 3600));
                flash($ok ? "Lien de connexion envoyé à {$member['email']}." : "L’envoi à {$member['email']} a échoué.");
                redirect('/reglages#jury');

            case 'member_logout_all':
                db_exec('DELETE FROM sessions WHERE member_id = ?', [$id]);
                flash('Toutes les sessions de ce membre ont été fermées.');
                redirect('/reglages#jury');

            case 'scores_reset':
                if (trim($_POST['confirm'] ?? '') !== 'EFFACER') {
                    throw new InvalidArgumentException('Réinitialisation annulée : saisissez EFFACER pour confirmer.');
                }
                $n = db_exec('DELETE FROM scores');
                error_log("Réinitialisation des réponses par {$me['name']} (#{$me['id']}) : {$n} note(s) supprimée(s)");
                flash("Toutes les réponses ont été effacées ({$n} note(s) supprimée(s)).");
                redirect('/reglages#danger');

            default:
                throw new InvalidArgumentException('Action inconnue.');
        }
    } catch (PDOException $ex) { // avant RuntimeException, dont elle hérite
        $notice = ($ex->errorInfo[1] ?? null) == 1062 ? 'Cette adresse e-mail est déjà utilisée.' : 'Erreur base de données : ' . $ex->getMessage();
    } catch (InvalidArgumentException | RuntimeException $ex) {
        $notice = $ex->getMessage();
    }
}

$sessionsCount = array_column(db_all('SELECT member_id, COUNT(*) AS n FROM sessions GROUP BY member_id'), 'n', 'member_id');
$scoresTotal = (int) db()->query('SELECT COUNT(*) FROM scores')->fetchColumn();
$scoresCount = array_column(db_all('SELECT member_id, COUNT(DISTINCT architect_id) AS n FROM scores GROUP BY member_id'), 'n', 'member_id');

render('settings', [
    'me'            => $me,
    'architects'    => get_architects(),
    'members'       => get_members(),
    'notice'        => $notice,
    'generatedLink' => $generatedLink,
    'sessionsCount' => $sessionsCount,
    'scoresCount'   => $scoresCount,
    'scoresTotal'   => $scoresTotal,
]);
