<?php
// ---------- 4.4 : gestion des commandes (annulation, avoir, modification), blocages de places, limite par acheteur ----------
return [
    "ALTER TABLE orders ADD COLUMN cancel_reason VARCHAR(255) NULL, ADD COLUMN credit_gift_id INT UNSIGNED NULL, ADD COLUMN parent_order_id INT UNSIGNED NULL, ADD INDEX parent (parent_order_id)",
    "ALTER TABLE events ADD COLUMN max_per_buyer INT NOT NULL DEFAULT 0",
    "CREATE TABLE IF NOT EXISTS place_holds (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_id INT UNSIGNED NOT NULL,
        rate_id INT UNSIGNED NULL,
        session_id INT UNSIGNED NULL,
        qty INT NOT NULL,
        label VARCHAR(120) NOT NULL,
        note VARCHAR(255) NULL,
        user_id INT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        released_at DATETIME NULL,
        INDEX(event_id, released_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
