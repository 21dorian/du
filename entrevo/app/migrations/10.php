<?php
// ---------- 1.9.0 : changer de date, inviter des amis, tarif dégressif, préventes privées, restriction d'âge ----------
return [
        "ALTER TABLE events ADD COLUMN exchange_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN exchange_hours INT NOT NULL DEFAULT 24, ADD COLUMN group_tiers VARCHAR(255) NULL, ADD COLUMN min_age TINYINT UNSIGNED NULL, ADD COLUMN presale_until DATETIME NULL, ADD COLUMN presale_past TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN presale_list MEDIUMTEXT NULL",
        "ALTER TABLE rates ADD COLUMN min_age TINYINT UNSIGNED NULL",
        "ALTER TABLE orders ADD COLUMN group_discount INT NOT NULL DEFAULT 0, ADD COLUMN referrer_order_id INT UNSIGNED NULL, ADD COLUMN exchanges INT NOT NULL DEFAULT 0, ADD COLUMN birth_date DATE NULL, ADD INDEX(referrer_order_id)",
        "CREATE TABLE IF NOT EXISTS presale_access (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            reason VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            UNIQUE KEY (event_id, email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
