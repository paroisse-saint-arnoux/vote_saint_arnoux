-- Schéma de l'outil de vote — sélection des architectes
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS members (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(190) NOT NULL,
    email       VARCHAR(190) NOT NULL,
    -- votant : compte dans le score ; consultatif : note mais ne compte que pour les écarts types ;
    -- aucun : pas membre du jury (ex. administrateur seul)
    role        ENUM('votant', 'consultatif', 'aucun') NOT NULL DEFAULT 'votant',
    is_admin    TINYINT(1) NOT NULL DEFAULT 0,
    position    INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_members_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS architects (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency      VARCHAR(255) NOT NULL,
    referent    VARCHAR(255) NOT NULL DEFAULT '',
    city        VARCHAR(255) NOT NULL DEFAULT '',
    website     VARCHAR(500) NOT NULL DEFAULT '',
    drive_url   VARCHAR(500) NOT NULL DEFAULT '',
    -- points de vigilance du MOD : 0 = pas de souci, 1 = incohérences (⚠️), 2 = gros souci (⛔)
    check_grouping  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    check_financial TINYINT UNSIGNED NOT NULL DEFAULT 0,
    check_insurance TINYINT UNSIGNED NOT NULL DEFAULT 0,
    -- clé aléatoire fixée à la création : ordre aléatoire mais identique pour tous
    sort_key    INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_architects_sort (sort_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une ligne par (membre, architecte, critère) évalué. Absence de ligne = « Non évalué ».
CREATE TABLE IF NOT EXISTS scores (
    member_id     INT UNSIGNED NOT NULL,
    architect_id  INT UNSIGNED NOT NULL,
    criterion     TINYINT UNSIGNED NOT NULL,
    score         TINYINT UNSIGNED NOT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (member_id, architect_id, criterion),
    KEY idx_scores_architect (architect_id),
    CONSTRAINT fk_scores_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE,
    CONSTRAINT fk_scores_architect FOREIGN KEY (architect_id) REFERENCES architects (id) ON DELETE CASCADE,
    CONSTRAINT chk_scores_range CHECK (score BETWEEN 0 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jetons de connexion (liens magiques). Seul le hash SHA-256 est stocké.
CREATE TABLE IF NOT EXISTS login_tokens (
    token_hash  CHAR(64) NOT NULL PRIMARY KEY,
    member_id   INT UNSIGNED NOT NULL,
    expires_at  DATETIME NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tokens_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions persistantes (cookie sans expiration pratique). Seul le hash est stocké.
CREATE TABLE IF NOT EXISTS sessions (
    token_hash  CHAR(64) NOT NULL PRIMARY KEY,
    member_id   INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sessions_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
