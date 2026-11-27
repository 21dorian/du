<?php
return [
        "CREATE TABLE IF NOT EXISTS credit_notes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED NOT NULL,
            number VARCHAR(30) NOT NULL,
            amount INT NOT NULL,
            reason VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY (organizer_id, number), INDEX(order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE organizers ADD COLUMN credit_seq INT NOT NULL DEFAULT 0, ADD COLUMN auto_invoice TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN accounting_json TEXT NULL,
            ADD COLUMN site_json TEXT NULL, ADD COLUMN custom_domain VARCHAR(190) NULL, ADD COLUMN domain_status VARCHAR(20) NULL, ADD COLUMN domain_checked_at DATETIME NULL,
            ADD COLUMN white_label TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN wl_json TEXT NULL, ADD UNIQUE KEY (custom_domain)",
        "CREATE TABLE IF NOT EXISTS reports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            title VARCHAR(190) NOT NULL,
            event_ids VARCHAR(1000) NULL,
            date_from DATE NULL,
            date_to DATE NULL,
            sections_json MEDIUMTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX(organizer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS site_pages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            kind ENUM('page','news') NOT NULL DEFAULT 'page',
            slug VARCHAR(120) NOT NULL,
            title VARCHAR(190) NOT NULL,
            excerpt VARCHAR(500) NULL,
            body MEDIUMTEXT NULL,
            image VARCHAR(255) NULL,
            in_menu TINYINT(1) NOT NULL DEFAULT 1,
            sort INT NOT NULL DEFAULT 0,
            published TINYINT(1) NOT NULL DEFAULT 1,
            published_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY (organizer_id, kind, slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS site_contacts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            name VARCHAR(190) NULL,
            source VARCHAR(30) NULL,
            created_at DATETIME NOT NULL,
            unsub_at DATETIME NULL,
            UNIQUE KEY (organizer_id, email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS site_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            subject VARCHAR(190) NULL,
            body TEXT NOT NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL,
            read_at DATETIME NULL,
            INDEX(organizer_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
