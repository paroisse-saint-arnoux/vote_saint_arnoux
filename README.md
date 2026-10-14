# Vote Saint-Arnoux — sélection des architectes

Outil de notation des dossiers de candidature d’architectes par le jury (PHP 8 / MySQL 8, sans dépendance).

## Fonctionnalités

- **Connexion par lien magique** (pas de mot de passe), puis cookie de session persistant (10 ans).
- **Accueil** : classement en direct + liste des dossiers dans un ordre aléatoire, identique pour tous.
- **Grille de notation** : 6 critères pondérés, échelle *Non évalué / 0 à 5*, enregistrement automatique.
- **Tableau de bord par architecte** (rafraîchi toutes les 3 s) : moyennes, écarts types colorés, une ligne par juré.
- **Réglages** (administrateurs) : étape de la consultation, architectes (dont liens Google Drive), import TSV, jury, liens de connexion.
- **Deux étapes** : l’étape 1 (sélection des candidatures) et l’étape 2 (livrables des 10 premiers de l’étape 1,
  sélection figée au passage à l’étape 2). Chaque étape a sa grille et ses notes. À l’étape 2, les critères 1 et 2
  sont notés par le seul **MOD** (note reprise pour tout le jury), les autres critères se notent dans l’ordre,
  chacun sur tous les dossiers, et chaque note peut être accompagnée d’un commentaire personnel.

## Règles de calcul

- Score sur 100 = Σ(poids × note) / Σ(poids des critères notés) × 20. Un critère *Non évalué* est exclu
  et les poids restants sont renormalisés.
- **Score officiel** d’un architecte : calculé sur la moyenne de chaque critère des seuls membres **votants**.
- La ligne « Moyenne du jury » et les **écarts types** incluent les membres **consultatifs**.
- Écart type (population) : vert < 0,75 ; orange 0,75–1,25 ; rouge > 1,25 (×20 pour la colonne score / 100).

## Développement

```bash
cp .env.example .env
docker compose up -d --build
cp config/jury.example.php config/jury.local.php   # puis y saisir le jury réel
docker compose exec web php scripts/setup.php   # tables + jury initial + import du TSV d’architectes/
```

| Service  | URL                    |
|----------|------------------------|
| Appli    | http://localhost:8080  |
| DbGate   | http://localhost:8081  |
| Mailpit  | http://localhost:8025 (e-mails de connexion) |

Pour se connecter sans passer par l’e-mail :

```bash
docker compose exec web php -r 'require "src/bootstrap.php"; echo create_login_link(2, 3600), "\n";'
```

## Déploiement sur O2Switch

1. Créer la base MySQL et son utilisateur dans cPanel.
2. Envoyer le dépôt (hors `.git`, `docker/`) sur l’hébergement, par exemple dans `~/vote/`.
3. Faire pointer le domaine ou sous-domaine sur **`~/vote/public`** (racine du document).
4. Copier `config/config.local.php.example` en `config/config.local.php` et le compléter
   (`app_url` en https, accès base, `mail_from` sur une adresse du domaine pour la délivrabilité,
   et paramètres `smtp_*` du compte e-mail correspondant créé dans cPanel).
5. Déposer `config/jury.local.php` et le TSV (non versionnés) puis initialiser : `php ~/vote/scripts/setup.php` (terminal cPanel / SSH), ou importer `sql/schema.sql`
   dans phpMyAdmin puis importer le TSV depuis la page Réglages.
6. Activer le certificat SSL (AutoSSL) : le cookie de session est alors marqué `Secure`.

### Mettre à jour la production

1. Sauvegarder la base (phpMyAdmin → Exporter).
2. Envoyer le nouveau code. Tant que la base n’est pas migrée, l’outil affiche une page « Mise à jour en cours »
   (les membres du jury ne peuvent rien modifier entre-temps).
3. Appliquer les migrations, au choix :
   - en SSH / terminal cPanel : `php ~/vote/scripts/migrate.php` (`--status` pour seulement lister) ;
   - dans le navigateur, connecté en administrateur : bouton « Mettre à jour la base » de la page de maintenance.

En cas d’erreur 500, consulter `~/vote/logs/php-error.log` (gestionnaire de fichiers cPanel).

Si les e-mails de connexion n’arrivent pas, lancer le diagnostic (configuration, DNS/SPF, échange SMTP complet) :

```bash
php ~/vote/scripts/test-mail.php vous@example.org               # message de test
php ~/vote/scripts/test-mail.php vous@example.org --connexion   # vrai lien de connexion d’un juré
```

## Migrations du schéma

`sql/schema.sql` est le schéma initial. Toute modification ultérieure passe par un nouveau fichier dans
`sql/migrations/`, numéroté à la suite (`003_description.sql`), jamais par une modification de `schema.sql`
ni d’une migration déjà déployée. Les migrations sont appliquées une seule fois, dans l’ordre des noms, et
enregistrées dans la table `migrations` :

- `.sql` : requêtes SQL, chacune terminée par `;` en fin de ligne ;
- `.php` : fichier renvoyant une fonction, pour une migration qui doit d’abord vérifier l’état de la base.

En local : `docker compose exec web php scripts/migrate.php` (aussi appelé par `setup.php`).

## Structure

```
public/        racine web : index.php (routeur), assets/
src/           logique : auth, scores, import, réglages
templates/     vues PHP
config/        configuration (config.local.php non versionné)
sql/schema.sql schéma initial de la base
sql/migrations modifications du schéma (voir ci-dessus)
scripts/       setup.php (initialisation idempotente), migrate.php (migrations)
architectes/   fichier TSV source
```
