<?php
return [
        "CREATE TABLE IF NOT EXISTS pay_zones (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            name VARCHAR(80) NOT NULL,
            profiles VARCHAR(255) NOT NULL DEFAULT '',
            capacity INT NOT NULL DEFAULT 0,
            color VARCHAR(7) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS pay_zone_scans (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            zone_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            wallet_id INT UNSIGNED NULL,
            direction VARCHAR(3) NOT NULL,
            result VARCHAR(12) NOT NULL,
            reason VARCHAR(120) NULL,
            device_id INT UNSIGNED NULL,
            staff VARCHAR(60) NULL,
            created_at DATETIME NOT NULL,
            INDEX(zone_id, created_at), INDEX(wallet_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE pos_devices ADD COLUMN zone_id INT UNSIGNED NULL",
];
