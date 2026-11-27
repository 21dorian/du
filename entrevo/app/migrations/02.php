<?php
// ---------- 1.1.0 : séances, tarifs avancés, revente, liste d'attente, accréditations, plan de salle, API… ----------
return [
        "CREATE TABLE IF NOT EXISTS sessions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            label VARCHAR(190) NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NULL,
            capacity INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            INDEX(event_id, starts_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            description VARCHAR(500) NULL,
            price INT NOT NULL DEFAULT 0,
            stock INT NOT NULL DEFAULT 0,
            max_per_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort INT NOT NULL DEFAULT 0,
            INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS waitlist (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            session_id INT UNSIGNED NULL,
            email VARCHAR(190) NOT NULL,
            first_name VARCHAR(120) NULL,
            last_name VARCHAR(120) NULL,
            qty INT NOT NULL DEFAULT 1,
            status ENUM('waiting','offered','converted','expired','cancelled') NOT NULL DEFAULT 'waiting',
            token VARCHAR(64) NOT NULL,
            offered_at DATETIME NULL,
            expires_at DATETIME NULL,
            order_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            INDEX(event_id, status), UNIQUE KEY tok (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS resales (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            seller_order_id INT UNSIGNED NOT NULL,
            price INT NOT NULL,
            fee INT NOT NULL DEFAULT 0,
            status ENUM('listed','sold','cancelled') NOT NULL DEFAULT 'listed',
            buyer_order_id INT UNSIGNED NULL,
            refund_status VARCHAR(20) NULL,
            refund_info VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            sold_at DATETIME NULL,
            INDEX(event_id, status), INDEX(ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS partners (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NULL,
            token VARCHAR(64) NOT NULL,
            rate_id INT UNSIGNED NOT NULL,
            quota INT NOT NULL DEFAULT 0,
            free TINYINT(1) NOT NULL DEFAULT 1,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            UNIQUE KEY tok (token), INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS populations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            color VARCHAR(7) NOT NULL DEFAULT '#ff5a4e',
            access_point_ids VARCHAR(500) NULL,
            INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            subject VARCHAR(190) NOT NULL,
            body MEDIUMTEXT NOT NULL,
            filters VARCHAR(500) NULL,
            recipients INT NOT NULL DEFAULT 0,
            sent INT NOT NULL DEFAULT 0,
            failed INT NOT NULL DEFAULT 0,
            user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS seat_holds (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            session_id INT UNSIGNED NOT NULL DEFAULT 0,
            seat VARCHAR(40) NOT NULL,
            order_id INT UNSIGNED NOT NULL,
            expires_at DATETIME NOT NULL,
            UNIQUE KEY seat (event_id, session_id, seat), INDEX(order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS queue_entries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            admitted_at DATETIME NULL,
            last_seen DATETIME NOT NULL,
            UNIQUE KEY tok (token), INDEX(event_id, admitted_at, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS cash_closures (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            period_start DATETIME NOT NULL,
            period_end DATETIME NOT NULL,
            totals MEDIUMTEXT NOT NULL,
            counted_cash INT NULL,
            note VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS webhook_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(20) NOT NULL,
            ref VARCHAR(190) NULL,
            result VARCHAR(190) NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE events
            ADD COLUMN password VARCHAR(190) NULL,
            ADD COLUMN publish_at DATETIME NULL,
            ADD COLUMN resale_enabled TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN resale_fee_percent DECIMAL(5,2) NOT NULL DEFAULT 5,
            ADD COLUMN waitlist_enabled TINYINT(1) NOT NULL DEFAULT 1,
            ADD COLUMN refund_request_enabled TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN refund_deadline DATETIME NULL,
            ADD COLUMN donation_enabled TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN donation_label VARCHAR(190) NULL,
            ADD COLUMN seatmap MEDIUMTEXT NULL,
            ADD COLUMN ticket_color VARCHAR(7) NULL,
            ADD COLUMN ticket_logo VARCHAR(255) NULL,
            ADD COLUMN email_message TEXT NULL,
            ADD COLUMN queue_enabled TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN queue_rate INT NOT NULL DEFAULT 50,
            ADD COLUMN category VARCHAR(60) NULL",

        "ALTER TABLE rates
            ADD COLUMN price_mode ENUM('fixed','free') NOT NULL DEFAULT 'fixed',
            ADD COLUMN pack_size INT NOT NULL DEFAULT 1,
            ADD COLUMN max_entries INT NOT NULL DEFAULT 1,
            ADD COLUMN per_day TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN session_ids VARCHAR(500) NULL,
            ADD COLUMN seat_category VARCHAR(60) NULL",

        "ALTER TABLE form_fields ADD COLUMN rate_ids VARCHAR(500) NULL",

        "ALTER TABLE orders
            MODIFY method VARCHAR(20) NOT NULL DEFAULT 'online',
            ADD COLUMN session_id INT UNSIGNED NULL,
            ADD COLUMN channel VARCHAR(30) NULL,
            ADD COLUMN utm_source VARCHAR(120) NULL,
            ADD COLUMN utm_medium VARCHAR(120) NULL,
            ADD COLUMN utm_campaign VARCHAR(120) NULL,
            ADD COLUMN partner_id INT UNSIGNED NULL,
            ADD COLUMN donation INT NOT NULL DEFAULT 0,
            ADD COLUMN refund_requested_at DATETIME NULL,
            ADD COLUMN refund_reason VARCHAR(500) NULL,
            ADD COLUMN resale_id INT UNSIGNED NULL,
            ADD COLUMN waitlist_id INT UNSIGNED NULL,
            ADD COLUMN refunded_amount INT NOT NULL DEFAULT 0,
            ADD INDEX (session_id)",

        "ALTER TABLE order_items
            ADD COLUMN kind ENUM('rate','product') NOT NULL DEFAULT 'rate',
            ADD COLUMN product_id INT UNSIGNED NULL,
            ADD COLUMN seats VARCHAR(1000) NULL",

        "ALTER TABLE tickets
            ADD COLUMN session_id INT UNSIGNED NULL,
            ADD COLUMN seat VARCHAR(40) NULL,
            ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'ticket',
            ADD COLUMN population_id INT UNSIGNED NULL,
            ADD COLUMN company VARCHAR(190) NULL,
            ADD COLUMN max_entries INT NOT NULL DEFAULT 1,
            ADD COLUMN per_day TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN resale_status VARCHAR(20) NULL,
            ADD COLUMN replaced_by INT UNSIGNED NULL,
            ADD INDEX (session_id)",

        "ALTER TABLE scans ADD COLUMN session_id INT UNSIGNED NULL",

        "ALTER TABLE organizers
            ADD COLUMN logo VARCHAR(255) NULL,
            ADD COLUMN ga4_id VARCHAR(40) NULL,
            ADD COLUMN gtm_id VARCHAR(40) NULL,
            ADD COLUMN fb_pixel VARCHAR(40) NULL,
            ADD COLUMN api_key VARCHAR(80) NULL,
            ADD COLUMN webhook_url VARCHAR(255) NULL,
            ADD COLUMN webhook_secret VARCHAR(80) NULL",
];
