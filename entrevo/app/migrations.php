<?php
// Évolutions de la base de données. Chaque mise à jour peut ajouter une entrée (numéro suivant).
// Elles sont appliquées automatiquement au premier chargement après une mise à jour.
return [
    1 => [
        "CREATE TABLE IF NOT EXISTS settings (
            k VARCHAR(64) PRIMARY KEY,
            v MEDIUMTEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS organizers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL UNIQUE,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(40) NULL,
            address VARCHAR(255) NULL,
            legal_name VARCHAR(190) NULL,
            siret VARCHAR(40) NULL,
            vat_number VARCHAR(40) NULL,
            iban VARCHAR(64) NULL,
            bic VARCHAR(20) NULL,
            description TEXT NULL,
            website VARCHAR(255) NULL,
            fee_mode ENUM('buyer','organizer') NOT NULL DEFAULT 'buyer',
            fee_percent DECIMAL(5,2) NULL,
            fee_fixed INT NULL,
            status ENUM('active','suspended') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            name VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('superadmin','owner','manager','scanner') NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            reset_token VARCHAR(64) NULL,
            reset_expires DATETIME NULL,
            last_login DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX(organizer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS events (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            slug VARCHAR(190) NOT NULL UNIQUE,
            name VARCHAR(190) NOT NULL,
            description MEDIUMTEXT NULL,
            venue VARCHAR(190) NULL,
            address VARCHAR(255) NULL,
            city VARCHAR(120) NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NULL,
            image VARCHAR(255) NULL,
            capacity INT NOT NULL DEFAULT 0,
            max_per_order INT NOT NULL DEFAULT 10,
            allow_reentry TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
            ticket_info TEXT NULL,
            created_at DATETIME NOT NULL,
            INDEX(organizer_id), INDEX(status, starts_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS rates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            description VARCHAR(500) NULL,
            price INT NOT NULL DEFAULT 0,
            quota INT NOT NULL DEFAULT 0,
            max_per_order INT NOT NULL DEFAULT 0,
            sale_start DATETIME NULL,
            sale_end DATETIME NULL,
            hidden TINYINT(1) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort INT NOT NULL DEFAULT 0,
            INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS promos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            code VARCHAR(64) NOT NULL,
            kind ENUM('percent','amount','unlock') NOT NULL DEFAULT 'percent',
            value INT NOT NULL DEFAULT 0,
            rate_ids VARCHAR(500) NULL,
            max_uses INT NOT NULL DEFAULT 0,
            used INT NOT NULL DEFAULT 0,
            valid_until DATETIME NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY ev_code (event_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS form_fields (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            label VARCHAR(190) NOT NULL,
            type ENUM('text','textarea','select','checkbox','date','email','phone','number') NOT NULL DEFAULT 'text',
            options TEXT NULL,
            required TINYINT(1) NOT NULL DEFAULT 0,
            per_ticket TINYINT(1) NOT NULL DEFAULT 0,
            sort INT NOT NULL DEFAULT 0,
            INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ref VARCHAR(16) NOT NULL UNIQUE,
            token VARCHAR(64) NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            organizer_id INT UNSIGNED NOT NULL,
            email VARCHAR(190) NULL,
            first_name VARCHAR(120) NULL,
            last_name VARCHAR(120) NULL,
            phone VARCHAR(40) NULL,
            subtotal INT NOT NULL DEFAULT 0,
            discount INT NOT NULL DEFAULT 0,
            fees INT NOT NULL DEFAULT 0,
            total INT NOT NULL DEFAULT 0,
            fee_mode ENUM('buyer','organizer') NOT NULL DEFAULT 'buyer',
            commission INT NOT NULL DEFAULT 0,
            organizer_amount INT NOT NULL DEFAULT 0,
            promo_code VARCHAR(64) NULL,
            method ENUM('online','guichet','invitation') NOT NULL DEFAULT 'online',
            provider VARCHAR(20) NULL,
            provider_ref VARCHAR(190) NULL,
            provider_payment VARCHAR(190) NULL,
            pay_method VARCHAR(40) NULL,
            status ENUM('pending','paid','cancelled','refunded','expired') NOT NULL DEFAULT 'pending',
            answers MEDIUMTEXT NULL,
            note VARCHAR(500) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            paid_at DATETIME NULL,
            cancelled_at DATETIME NULL,
            email_sent_at DATETIME NULL,
            INDEX(event_id, status), INDEX(organizer_id, status), INDEX(email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS order_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            rate_id INT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            qty INT NOT NULL,
            unit_price INT NOT NULL,
            INDEX(order_id), INDEX(rate_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS tickets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            rate_id INT UNSIGNED NOT NULL,
            rate_name VARCHAR(190) NOT NULL,
            code VARCHAR(20) NOT NULL UNIQUE,
            first_name VARCHAR(120) NULL,
            last_name VARCHAR(120) NULL,
            price INT NOT NULL DEFAULT 0,
            answers MEDIUMTEXT NULL,
            status ENUM('valid','cancelled') NOT NULL DEFAULT 'valid',
            inside TINYINT(1) NOT NULL DEFAULT 0,
            entries INT NOT NULL DEFAULT 0,
            checked_in_at DATETIME NULL,
            last_scan_at DATETIME NULL,
            updated_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX(order_id), INDEX(event_id, status), INDEX(rate_id), INDEX(event_id, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS access_points (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            rate_ids VARCHAR(500) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS scans (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(64) NOT NULL UNIQUE,
            event_id INT UNSIGNED NOT NULL,
            ticket_id INT UNSIGNED NULL,
            code VARCHAR(64) NOT NULL,
            access_point_id INT UNSIGNED NULL,
            direction ENUM('in','out') NOT NULL DEFAULT 'in',
            result VARCHAR(20) NOT NULL,
            user_id INT UNSIGNED NULL,
            device VARCHAR(64) NULL,
            offline TINYINT(1) NOT NULL DEFAULT 0,
            undone TINYINT(1) NOT NULL DEFAULT 0,
            scanned_at DATETIME NOT NULL,
            received_at DATETIME NOT NULL,
            INDEX(event_id, scanned_at), INDEX(ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS payouts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            amount INT NOT NULL,
            reference VARCHAR(190) NULL,
            note VARCHAR(500) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            INDEX(organizer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS update_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(20) NOT NULL,
            previous VARCHAR(20) NULL,
            action ENUM('install','rollback') NOT NULL DEFAULT 'install',
            notes TEXT NULL,
            backup VARCHAR(190) NULL,
            user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],

    // ---------- 1.1.0 : séances, tarifs avancés, revente, liste d'attente, accréditations, plan de salle, API… ----------
    2 => [
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
    ],
    // ---------- 1.2.0 : statistiques de visites, journal d'activité, étiquettes de commande ----------
    3 => [
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
    ],
    // ---------- 1.2.0 : nouvelle charte (bleu électrique) si l'ancienne couleur par défaut était gardée ----------
    4 => [
        "UPDATE settings SET v = '#0032fa' WHERE k = 'primary_color' AND LOWER(v) IN ('#ff5a4e', '#5b3df5')",
    ],
    // ---------- 1.3.0 : staff & bénévoles (préparé), votes pour les modules à venir, objectif de recettes ----------
    5 => [
        "CREATE TABLE IF NOT EXISTS staff_members (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            first_name VARCHAR(80) NOT NULL,
            last_name VARCHAR(80) NOT NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(40) NULL,
            team VARCHAR(80) NULL,
            skills VARCHAR(255) NULL,
            tshirt VARCHAR(10) NULL,
            notes TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            token VARCHAR(40) NOT NULL,
            created_at DATETIME NOT NULL,
            last_seen_at DATETIME NULL,
            UNIQUE KEY (token), INDEX(organizer_id, status), INDEX(organizer_id, email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS staff_shifts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            team VARCHAR(80) NULL,
            title VARCHAR(120) NOT NULL,
            location VARCHAR(120) NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            needed INT NOT NULL DEFAULT 1,
            description TEXT NULL,
            open_signup TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            INDEX(event_id, starts_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS staff_assignments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shift_id INT UNSIGNED NOT NULL,
            member_id INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'proposed',
            checked_in_at DATETIME NULL,
            checked_out_at DATETIME NULL,
            reminder_sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY (shift_id, member_id), INDEX(member_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE events
            ADD COLUMN volunteer_recruit TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN volunteer_intro TEXT NULL",

        "CREATE TABLE IF NOT EXISTS feature_votes (
            organizer_id INT UNSIGNED NOT NULL,
            feature VARCHAR(40) NOT NULL,
            user_id INT UNSIGNED NULL,
            comment VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (organizer_id, feature)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE events
            ADD COLUMN revenue_goal INT NULL",
    ],
    // ---------- 1.4.0 : campagnes e-mail, consignes de scan, appareils, récaps, validation des organisateurs ----------
    6 => [
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
    ],
];
