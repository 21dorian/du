<?php
// ---------- 1.2.0 : statistiques de visites, journal d'activité, étiquettes de commande ----------
return [
        "CREATE TABLE IF NOT EXISTS event_traffic (
            event_id INT UNSIGNED NOT NULL,
            day DATE NOT NULL,
            views INT NOT NULL DEFAULT 0,
            visitors INT NOT NULL DEFAULT 0,
            carts INT NOT NULL DEFAULT 0,
            checkouts INT NOT NULL DEFAULT 0,
            PRIMARY KEY (event_id, day)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS audit_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NULL,
            event_id INT UNSIGNED NULL,
            order_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NULL,
            action VARCHAR(60) NOT NULL,
            details VARCHAR(500) NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL,
            INDEX(organizer_id, created_at), INDEX(event_id), INDEX(order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE orders
            ADD COLUMN tags VARCHAR(255) NULL,
            ADD COLUMN internal_note TEXT NULL",
];
