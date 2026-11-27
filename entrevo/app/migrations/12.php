<?php
// ---------- 2.0.0 (vague 2) : liens de suivi & ambassadeurs, page événement enrichie, page organisateur ----------
return [
        "CREATE TABLE IF NOT EXISTS tracking_links (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            code VARCHAR(24) NOT NULL,
            kind VARCHAR(12) NOT NULL DEFAULT 'canal',
            email VARCHAR(190) NULL,
            promo_code VARCHAR(64) NULL,
            reward VARCHAR(190) NULL,
            token VARCHAR(40) NOT NULL,
            clicks INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            UNIQUE KEY (event_id, code), INDEX(organizer_id), INDEX(token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE orders ADD COLUMN link_id INT UNSIGNED NULL, ADD INDEX(link_id)",
        "ALTER TABLE events ADD COLUMN program MEDIUMTEXT NULL, ADD COLUMN faq MEDIUMTEXT NULL, ADD COLUMN practical MEDIUMTEXT NULL",
        "ALTER TABLE organizers ADD COLUMN cover VARCHAR(255) NULL, ADD COLUMN accent VARCHAR(7) NULL, ADD COLUMN socials TEXT NULL, ADD COLUMN tagline VARCHAR(190) NULL, ADD COLUMN show_past TINYINT(1) NOT NULL DEFAULT 1",
];
