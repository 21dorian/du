<?php
// ---------- 4.3 : appli organisateur (notifications) et contrôle d'accès pro (bracelets NFC, accusés de lecture) ----------
return [
    "CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        organizer_id INT UNSIGNED NOT NULL,
        endpoint VARCHAR(700) NOT NULL,
        endpoint_hash CHAR(40) NOT NULL,
        p256dh VARCHAR(140) NOT NULL,
        auth VARCHAR(60) NOT NULL,
        prefs TEXT NULL,
        device VARCHAR(120) NULL,
        created_at DATETIME NOT NULL,
        last_ok_at DATETIME NULL,
        fails INT NOT NULL DEFAULT 0,
        UNIQUE KEY (endpoint_hash), INDEX(organizer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS push_queue (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        subscription_id INT UNSIGNED NOT NULL,
        payload TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        sent_at DATETIME NULL,
        status INT NULL,
        INDEX(sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS app_alerts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        organizer_id INT UNSIGNED NOT NULL,
        event_id INT UNSIGNED NULL,
        kind VARCHAR(20) NOT NULL,
        title VARCHAR(190) NOT NULL,
        body VARCHAR(300) NULL,
        url VARCHAR(255) NULL,
        dedup VARCHAR(80) NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY (organizer_id, dedup), INDEX(organizer_id, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "ALTER TABLE tickets ADD COLUMN nfc_uid VARCHAR(32) NULL, ADD INDEX ev_nfc (event_id, nfc_uid)",
    "ALTER TABLE scan_messages ADD COLUMN must_ack TINYINT(1) NOT NULL DEFAULT 0",
    "CREATE TABLE IF NOT EXISTS scan_acks (
        message_id INT UNSIGNED NOT NULL,
        device VARCHAR(40) NOT NULL,
        user_id INT UNSIGNED NULL,
        acked_at DATETIME NOT NULL,
        PRIMARY KEY (message_id, device)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "ALTER TABLE scan_devices ADD COLUMN queue INT NULL",
];
