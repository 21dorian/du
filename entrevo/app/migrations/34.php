<?php
return [
        "ALTER TABLE orders ADD COLUMN resa_reminded TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE rates ADD COLUMN ticket_image VARCHAR(190) NULL, ADD COLUMN name_en VARCHAR(190) NULL, ADD COLUMN description_en VARCHAR(255) NULL",
        "ALTER TABLE events ADD COLUMN name_en VARCHAR(190) NULL, ADD COLUMN description_en TEXT NULL, ADD COLUMN ticket_info_en TEXT NULL",
        "ALTER TABLE orders ADD COLUMN lang CHAR(2) NULL",
        "ALTER TABLE events ADD COLUMN click_collect TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN click_collect_bars VARCHAR(255) NULL",
        "ALTER TABLE pos_sales ADD COLUMN source VARCHAR(10) NULL",
        "CREATE TABLE IF NOT EXISTS staff_provisions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            member_id INT UNSIGNED NOT NULL,
            meals INT NOT NULL DEFAULT 0,
            meals_used INT NOT NULL DEFAULT 0,
            lodging VARCHAR(190) NULL,
            items VARCHAR(255) NULL,
            given_at DATETIME NULL,
            UNIQUE KEY (event_id, member_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS staff_docs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NULL,
            team VARCHAR(80) NULL,
            title VARCHAR(190) NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX(organizer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS staff_signatures (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            doc_id INT UNSIGNED NOT NULL,
            member_id INT UNSIGNED NOT NULL,
            name VARCHAR(160) NOT NULL,
            ip VARCHAR(45) NULL,
            hash CHAR(64) NOT NULL,
            signed_at DATETIME NOT NULL,
            UNIQUE KEY (doc_id, member_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS ticket_stocks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            rate_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED NOT NULL,
            label VARCHAR(160) NOT NULL,
            qty INT NOT NULL,
            unit_price INT NOT NULL DEFAULT 0,
            sold INT NULL,
            closed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
