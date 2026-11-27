<?php
// ---------- 1.4.0 : campagnes e-mail, consignes de scan, appareils, récaps, validation des organisateurs ----------
return [
        "ALTER TABLE orders ADD COLUMN marketing_optin TINYINT(1) NOT NULL DEFAULT 0",

        "CREATE TABLE IF NOT EXISTS campaigns (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            name VARCHAR(160) NOT NULL,
            subject VARCHAR(190) NOT NULL DEFAULT '',
            preheader VARCHAR(190) NULL,
            blocks MEDIUMTEXT NULL,
            audience TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            scheduled_at DATETIME NULL,
            started_at DATETIME NULL,
            sent_at DATETIME NULL,
            recipients INT NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            INDEX(organizer_id, created_at), INDEX(status, scheduled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS campaign_recipients (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            first_name VARCHAR(80) NULL,
            token VARCHAR(32) NOT NULL,
            sent_at DATETIME NULL,
            opened_at DATETIME NULL,
            opens INT NOT NULL DEFAULT 0,
            clicked_at DATETIME NULL,
            clicks INT NOT NULL DEFAULT 0,
            unsubscribed_at DATETIME NULL,
            error VARCHAR(255) NULL,
            UNIQUE KEY (campaign_id, email), UNIQUE KEY (token), INDEX(campaign_id, sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS email_unsubscribes (
            organizer_id INT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            campaign_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (organizer_id, email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE rates
            ADD COLUMN scan_note VARCHAR(160) NULL,
            ADD COLUMN scan_color VARCHAR(7) NULL",

        "CREATE TABLE IF NOT EXISTS scan_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            body VARCHAR(280) NOT NULL,
            level VARCHAR(10) NOT NULL DEFAULT 'info',
            user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            INDEX(event_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS scan_devices (
            device VARCHAR(40) NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            ap_name VARCHAR(120) NULL,
            direction VARCHAR(4) NULL,
            pending INT NOT NULL DEFAULT 0,
            battery INT NULL,
            scans INT NOT NULL DEFAULT 0,
            last_seen DATETIME NOT NULL,
            PRIMARY KEY (device, event_id), INDEX(event_id, last_seen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE organizers
            ADD COLUMN recap VARCHAR(10) NULL,
            ADD COLUMN recap_sent_at DATETIME NULL,
            ADD COLUMN approved_at DATETIME NULL,
            ADD COLUMN admin_note TEXT NULL",

        "ALTER TABLE organizers MODIFY COLUMN status ENUM('active','suspended','pending') NOT NULL DEFAULT 'active'",

        "ALTER TABLE events ADD COLUMN alerts_sent VARCHAR(40) NULL",

        "ALTER TABLE payouts ADD COLUMN batch VARCHAR(40) NULL",
];
