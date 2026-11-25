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
    // ---------- 1.5.0 : KYC, factures de commission, TVA, budget, tâches, documents, jauges de zone ----------
    7 => [
        "CREATE TABLE IF NOT EXISTS org_documents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            kind VARCHAR(20) NOT NULL,
            file VARCHAR(255) NOT NULL,
            original_name VARCHAR(190) NULL,
            mime VARCHAR(80) NULL,
            size INT NOT NULL DEFAULT 0,
            status VARCHAR(12) NOT NULL DEFAULT 'pending',
            note VARCHAR(500) NULL,
            uploaded_at DATETIME NOT NULL,
            reviewed_at DATETIME NULL,
            reviewed_by INT UNSIGNED NULL,
            INDEX(organizer_id, kind), INDEX(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE organizers
            ADD COLUMN kyc_status VARCHAR(12) NOT NULL DEFAULT 'none',
            ADD COLUMN kyc_verified_at DATETIME NULL,
            ADD COLUMN vat_rate DECIMAL(4,2) NULL",

        "CREATE TABLE IF NOT EXISTS commission_invoices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            number VARCHAR(30) NOT NULL,
            period CHAR(7) NOT NULL,
            orders_count INT NOT NULL DEFAULT 0,
            amount_ht INT NOT NULL DEFAULT 0,
            vat_rate DECIMAL(4,2) NOT NULL DEFAULT 0,
            vat INT NOT NULL DEFAULT 0,
            amount_ttc INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            UNIQUE KEY (number), UNIQUE KEY (organizer_id, period)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE rates ADD COLUMN vat_rate DECIMAL(4,2) NULL",

        "CREATE TABLE IF NOT EXISTS budget_lines (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            kind VARCHAR(10) NOT NULL DEFAULT 'expense',
            category VARCHAR(60) NOT NULL,
            label VARCHAR(160) NOT NULL,
            planned INT NOT NULL DEFAULT 0,
            actual INT NULL,
            paid TINYINT(1) NOT NULL DEFAULT 0,
            supplier VARCHAR(120) NULL,
            note VARCHAR(500) NULL,
            sort INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX(event_id, kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS tasks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NULL,
            title VARCHAR(190) NOT NULL,
            description TEXT NULL,
            due_date DATE NULL,
            assignee_id INT UNSIGNED NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'todo',
            priority TINYINT NOT NULL DEFAULT 0,
            sort INT NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            done_at DATETIME NULL,
            INDEX(organizer_id, status), INDEX(event_id), INDEX(due_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS documents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NULL,
            title VARCHAR(190) NOT NULL,
            category VARCHAR(30) NOT NULL DEFAULT 'autre',
            file VARCHAR(255) NOT NULL,
            original_name VARCHAR(190) NULL,
            mime VARCHAR(80) NULL,
            size INT NOT NULL DEFAULT 0,
            expires_at DATE NULL,
            note VARCHAR(500) NULL,
            uploaded_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            INDEX(organizer_id, event_id), INDEX(expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE access_points ADD COLUMN capacity INT NULL",

        "CREATE TABLE IF NOT EXISTS zone_alerts (
            access_point_id INT UNSIGNED NOT NULL,
            level TINYINT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (access_point_id, level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    // ---------- 1.6.0 : intérêt du public pour les fonctions à venir ----------
    8 => [
        "CREATE TABLE IF NOT EXISTS public_interest (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            feature VARCHAR(40) NOT NULL,
            email VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            ip_hash CHAR(16) NULL,
            UNIQUE KEY (feature, email), INDEX(feature)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    // ---------- 1.8.0 : donner son billet, agenda & rappels, paniers abandonnés, avis, early bird ----------
    9 => [
        "ALTER TABLE tickets ADD COLUMN holder_email VARCHAR(190) NULL, ADD COLUMN holder_token VARCHAR(40) NULL, ADD COLUMN transfers INT NOT NULL DEFAULT 0, ADD COLUMN transferred_at DATETIME NULL, ADD INDEX(holder_token)",
        "CREATE TABLE IF NOT EXISTS ticket_transfers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            from_name VARCHAR(250) NULL,
            from_email VARCHAR(190) NULL,
            to_name VARCHAR(250) NOT NULL,
            to_email VARCHAR(190) NOT NULL,
            old_code VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX(ticket_id), INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE events ADD COLUMN transfer_enabled TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE orders ADD COLUMN reminder_sent_at DATETIME NULL, ADD COLUMN recovery_sent_at DATETIME NULL, ADD COLUMN recovered_from INT UNSIGNED NULL, ADD COLUMN review_sent_at DATETIME NULL",
        "ALTER TABLE organizers ADD COLUMN auto_reminder TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN auto_recovery TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN auto_review TINYINT(1) NOT NULL DEFAULT 1",
        "CREATE TABLE IF NOT EXISTS reviews (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            organizer_id INT UNSIGNED NOT NULL,
            rating TINYINT NOT NULL,
            comment VARCHAR(1000) NULL,
            author VARCHAR(120) NULL,
            verified TINYINT(1) NOT NULL DEFAULT 0,
            visible TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY (order_id), INDEX(organizer_id, visible), INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE rates ADD COLUMN after_rate_id INT UNSIGNED NULL",
    ],
    // ---------- 1.9.0 : changer de date, inviter des amis, tarif dégressif, préventes privées, restriction d'âge ----------
    10 => [
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
    ],
    // ---------- 2.0.0 : modules plus poussés ----------
    11 => [
        "ALTER TABLE promos ADD COLUMN valid_from DATETIME NULL, ADD COLUMN min_qty INT NOT NULL DEFAULT 0, ADD COLUMN once_per_email TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN batch VARCHAR(40) NULL, ADD COLUMN label VARCHAR(190) NULL, ADD INDEX(batch)",
        "ALTER TABLE products ADD COLUMN variants VARCHAR(500) NULL",
        "ALTER TABLE order_items ADD COLUMN variant VARCHAR(80) NULL",
        "ALTER TABLE events ADD COLUMN hold_minutes INT NULL, ADD COLUMN waitlist_hours INT NULL, ADD COLUMN reminder_message TEXT NULL, ADD COLUMN transfer_hours INT NOT NULL DEFAULT 0",
        "ALTER TABLE organizers ADD COLUMN reminder_hours INT NOT NULL DEFAULT 24, ADD COLUMN recovery_second TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN recovery_pct INT NOT NULL DEFAULT 0",
        "ALTER TABLE orders ADD COLUMN recovery2_sent_at DATETIME NULL",
        "ALTER TABLE reviews ADD COLUMN reply TEXT NULL, ADD COLUMN replied_at DATETIME NULL",
        "ALTER TABLE access_points ADD COLUMN opens_at DATETIME NULL, ADD COLUMN closes_at DATETIME NULL",
        "CREATE TABLE IF NOT EXISTS incidents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            level VARCHAR(10) NOT NULL DEFAULT 'info',
            category VARCHAR(30) NOT NULL DEFAULT 'autre',
            zone VARCHAR(120) NULL,
            title VARCHAR(190) NOT NULL,
            note TEXT NULL,
            author VARCHAR(120) NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            INDEX(event_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    // ---------- 2.0.0 (vague 2) : liens de suivi & ambassadeurs, page événement enrichie, page organisateur ----------
    12 => [
        "CREATE TABLE IF NOT EXISTS tracking_links (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            code VARCHAR(24) NOT NULL,
            kind VARCHAR(12) NOT NULL DEFAULT 'canal',
            email VARCHAR(190) NULL,
            promo_code VARCHAR(64) NULL,
            reward VARCHAR(190) NULL,
            token VARCHAR(40) NOT NULL,
            clicks INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            UNIQUE KEY (event_id, code), INDEX(organizer_id), INDEX(token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE orders ADD COLUMN link_id INT UNSIGNED NULL, ADD INDEX(link_id)",
        "ALTER TABLE events ADD COLUMN program MEDIUMTEXT NULL, ADD COLUMN faq MEDIUMTEXT NULL, ADD COLUMN practical MEDIUMTEXT NULL",
        "ALTER TABLE organizers ADD COLUMN cover VARCHAR(255) NULL, ADD COLUMN accent VARCHAR(7) NULL, ADD COLUMN socials TEXT NULL, ADD COLUMN tagline VARCHAR(190) NULL, ADD COLUMN show_past TINYINT(1) NOT NULL DEFAULT 1",
    ],
    // ---------- 2.0.0 : thème Apple (bleu par défaut) ----------
    13 => [
        "UPDATE settings SET v = '#0071e3' WHERE k = 'primary_color' AND LOWER(v) IN ('#0032fa', '')",
    ],
    // ---------- 2.1.0 : cartes cadeaux, abonnements & pass, boutique, SMS ----------
    14 => [
        "ALTER TABLE order_items MODIFY kind ENUM('rate','product','gift','pass','shop') NOT NULL DEFAULT 'rate', ADD COLUMN item_ref INT UNSIGNED NULL",
        "ALTER TABLE orders ADD COLUMN gift_code VARCHAR(32) NULL, ADD COLUMN gift_amount INT NOT NULL DEFAULT 0, ADD COLUMN pass_id INT UNSIGNED NULL, ADD COLUMN delivery VARCHAR(10) NULL, ADD COLUMN ship_address TEXT NULL, ADD COLUMN fulfilled_at DATETIME NULL",
        "CREATE TABLE IF NOT EXISTS gift_cards (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            code VARCHAR(32) NOT NULL,
            initial INT NOT NULL,
            balance INT NOT NULL,
            order_id INT UNSIGNED NULL,
            buyer_email VARCHAR(190) NULL,
            recipient_name VARCHAR(190) NULL,
            recipient_email VARCHAR(190) NULL,
            message VARCHAR(500) NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'active',
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY (code), INDEX(organizer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS gift_card_uses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            gift_card_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED NOT NULL,
            amount INT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX(gift_card_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS pass_types (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            description VARCHAR(500) NULL,
            kind VARCHAR(10) NOT NULL DEFAULT 'season',
            price INT NOT NULL DEFAULT 0,
            entries INT NULL,
            event_ids TEXT NULL,
            valid_until DATETIME NULL,
            quota INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX(organizer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS passes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            pass_type_id INT UNSIGNED NOT NULL,
            organizer_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED NULL,
            code VARCHAR(32) NOT NULL,
            token VARCHAR(40) NOT NULL,
            first_name VARCHAR(120) NULL,
            last_name VARCHAR(120) NULL,
            email VARCHAR(190) NULL,
            uses_left INT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            UNIQUE KEY (code), INDEX(token), INDEX(organizer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS shop_products (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            description VARCHAR(1000) NULL,
            price INT NOT NULL DEFAULT 0,
            stock INT NOT NULL DEFAULT 0,
            variants VARCHAR(500) NULL,
            image VARCHAR(255) NULL,
            delivery VARCHAR(10) NOT NULL DEFAULT 'pickup',
            ship_price INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX(organizer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE organizers ADD COLUMN gift_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN gift_amounts VARCHAR(100) NULL, ADD COLUMN gift_months INT NOT NULL DEFAULT 12, ADD COLUMN shop_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN pickup_info VARCHAR(500) NULL, ADD COLUMN sms_sender VARCHAR(11) NULL",
        "CREATE TABLE IF NOT EXISTS sms_campaigns (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            body VARCHAR(640) NOT NULL,
            audience TEXT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'draft',
            total INT NOT NULL DEFAULT 0,
            sent INT NOT NULL DEFAULT 0,
            failed INT NOT NULL DEFAULT 0,
            segments INT NOT NULL DEFAULT 1,
            cost INT NOT NULL DEFAULT 0,
            event_id INT UNSIGNED NULL,
            scheduled_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            INDEX(organizer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS sms_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT UNSIGNED NOT NULL,
            phone VARCHAR(20) NOT NULL,
            first_name VARCHAR(120) NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'queued',
            error VARCHAR(255) NULL,
            sent_at DATETIME NULL,
            INDEX(campaign_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS sms_optouts (
            organizer_id INT UNSIGNED NOT NULL,
            phone VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (organizer_id, phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    15 => [
        "CREATE TABLE IF NOT EXISTS plans (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            description VARCHAR(500) NULL,
            fee_percent DECIMAL(5,2) NULL,
            fee_fixed INT NULL,
            fee_cap INT NULL,
            monthly_price INT NOT NULL DEFAULT 0,
            sms_price INT NULL,
            modules TEXT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            public TINYINT(1) NOT NULL DEFAULT 1,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE organizers ADD COLUMN plan_id INT UNSIGNED NULL, ADD COLUMN plan_since DATE NULL, ADD COLUMN charges_billed_month CHAR(7) NULL",
        "CREATE TABLE IF NOT EXISTS contracts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            label VARCHAR(190) NOT NULL,
            fee_percent DECIMAL(5,2) NULL,
            fee_fixed INT NULL,
            fee_cap INT NULL,
            threshold INT NULL,
            fee_percent_after DECIMAL(5,2) NULL,
            starts_on DATE NOT NULL,
            ends_on DATE NULL,
            notes TEXT NULL,
            document VARCHAR(255) NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'active',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            INDEX(organizer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS org_charges (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            kind VARCHAR(16) NOT NULL,
            label VARCHAR(255) NOT NULL,
            amount INT NOT NULL,
            ref VARCHAR(64) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            INDEX(organizer_id), UNIQUE KEY uref (kind, ref)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS disputes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NULL,
            organizer_id INT UNSIGNED NOT NULL,
            provider VARCHAR(20) NULL,
            provider_ref VARCHAR(120) NULL,
            amount INT NOT NULL,
            bank_fee INT NOT NULL DEFAULT 0,
            reason VARCHAR(40) NOT NULL DEFAULT 'autre',
            status VARCHAR(12) NOT NULL DEFAULT 'open',
            due_on DATE NULL,
            evidence TEXT NULL,
            notes TEXT NULL,
            charge_id INT UNSIGNED NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            closed_at DATETIME NULL,
            INDEX(organizer_id), INDEX(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS support_tickets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ref VARCHAR(12) NOT NULL UNIQUE,
            token VARCHAR(40) NOT NULL,
            organizer_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NULL,
            name VARCHAR(190) NULL,
            email VARCHAR(190) NOT NULL,
            subject VARCHAR(190) NOT NULL,
            category VARCHAR(20) NOT NULL DEFAULT 'autre',
            priority VARCHAR(8) NOT NULL DEFAULT 'normal',
            status VARCHAR(10) NOT NULL DEFAULT 'open',
            order_ref VARCHAR(20) NULL,
            assigned_to INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            first_reply_at DATETIME NULL,
            solved_at DATETIME NULL,
            INDEX(status), INDEX(organizer_id), INDEX(email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS support_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT UNSIGNED NOT NULL,
            author VARCHAR(8) NOT NULL,
            user_id INT UNSIGNED NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX(ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS gdpr_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            kind VARCHAR(10) NOT NULL,
            message TEXT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'unverified',
            token VARCHAR(40) NOT NULL,
            notes TEXT NULL,
            handled_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            verified_at DATETIME NULL,
            due_on DATE NULL,
            done_at DATETIME NULL,
            INDEX(status), INDEX(email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) NULL, ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN failed_logins INT NOT NULL DEFAULT 0, ADD COLUMN locked_until DATETIME NULL, ADD COLUMN admin_perms VARCHAR(255) NULL, ADD COLUMN last_ip VARCHAR(45) NULL",
        "CREATE TABLE IF NOT EXISTS login_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            email VARCHAR(190) NOT NULL,
            ip VARCHAR(45) NULL,
            ua VARCHAR(255) NULL,
            result VARCHAR(16) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX(user_id), INDEX(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            sid VARCHAR(64) NOT NULL UNIQUE,
            ip VARCHAR(45) NULL,
            ua VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            revoked TINYINT(1) NOT NULL DEFAULT 0,
            INDEX(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    16 => [
        "CREATE TABLE IF NOT EXISTS alerts_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            akey VARCHAR(60) NOT NULL,
            level VARCHAR(8) NOT NULL DEFAULT 'warn',
            title VARCHAR(190) NOT NULL,
            body TEXT NULL,
            channels VARCHAR(60) NULL,
            created_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            INDEX(akey), INDEX(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS risk_blocks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(8) NOT NULL,
            value VARCHAR(190) NOT NULL,
            reason VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            UNIQUE KEY kv (kind, value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE orders ADD COLUMN ip VARCHAR(45) NULL, ADD COLUMN risk_score INT NOT NULL DEFAULT 0, ADD COLUMN risk_flags VARCHAR(255) NULL, ADD COLUMN risk_status VARCHAR(8) NULL, ADD INDEX (risk_status), ADD INDEX (ip)",
        "CREATE TABLE IF NOT EXISTS pages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(80) NOT NULL UNIQUE,
            title VARCHAR(190) NOT NULL,
            body MEDIUMTEXT NULL,
            in_footer TINYINT(1) NOT NULL DEFAULT 1,
            published TINYINT(1) NOT NULL DEFAULT 1,
            sort INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    17 => [
        "CREATE TABLE IF NOT EXISTS quotes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            ref VARCHAR(12) NOT NULL UNIQUE,
            token VARCHAR(40) NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'request',
            company VARCHAR(190) NULL,
            contact_name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(40) NULL,
            address VARCHAR(500) NULL,
            qty_wanted INT NOT NULL DEFAULT 0,
            message TEXT NULL,
            items_json TEXT NULL,
            discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
            valid_until DATE NULL,
            pay_mode VARCHAR(10) NOT NULL DEFAULT 'both',
            notes TEXT NULL,
            promo_code VARCHAR(64) NULL,
            order_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            accepted_at DATETIME NULL,
            INDEX(organizer_id), INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS artists (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            kind VARCHAR(40) NULL,
            contact_name VARCHAR(190) NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(40) NULL,
            fee INT NOT NULL DEFAULT 0,
            fee_status VARCHAR(10) NOT NULL DEFAULT 'todo',
            people INT NOT NULL DEFAULT 1,
            rider TEXT NULL,
            hospitality TEXT NULL,
            notes TEXT NULL,
            token VARCHAR(40) NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'option',
            created_at DATETIME NOT NULL,
            INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS performances (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            artist_id INT UNSIGNED NOT NULL,
            stage VARCHAR(80) NOT NULL DEFAULT 'Scène principale',
            arrival_at DATETIME NULL,
            soundcheck_at DATETIME NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            notes VARCHAR(500) NULL,
            INDEX(event_id), INDEX(artist_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS lost_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NULL,
            ref VARCHAR(10) NOT NULL,
            title VARCHAR(190) NOT NULL,
            category VARCHAR(30) NOT NULL DEFAULT 'autre',
            description VARCHAR(1000) NULL,
            found_where VARCHAR(190) NULL,
            found_at DATETIME NOT NULL,
            photo VARCHAR(255) NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'found',
            claimant VARCHAR(190) NULL,
            claimant_email VARCHAR(190) NULL,
            returned_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            INDEX(organizer_id), INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS lost_reports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NULL,
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(40) NULL,
            category VARCHAR(30) NOT NULL DEFAULT 'autre',
            description VARCHAR(1000) NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'open',
            item_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            INDEX(organizer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE orders ADD COLUMN invoice_number VARCHAR(30) NULL, ADD COLUMN invoice_company VARCHAR(190) NULL, ADD COLUMN invoice_address VARCHAR(500) NULL, ADD COLUMN invoice_vat VARCHAR(40) NULL, ADD COLUMN invoice_at DATETIME NULL, ADD COLUMN quote_id INT UNSIGNED NULL",
        "ALTER TABLE organizers ADD COLUMN invoice_seq INT NOT NULL DEFAULT 0, ADD COLUMN quotes_enabled TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN lost_enabled TINYINT(1) NOT NULL DEFAULT 1",
        "CREATE TABLE IF NOT EXISTS prospects (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            kind VARCHAR(30) NULL,
            contact_name VARCHAR(190) NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(40) NULL,
            city VARCHAR(120) NULL,
            website VARCHAR(255) NULL,
            source VARCHAR(40) NULL,
            stage VARCHAR(12) NOT NULL DEFAULT 'lead',
            est_volume INT NOT NULL DEFAULT 0,
            next_action VARCHAR(190) NULL,
            next_action_on DATE NULL,
            owner_id INT UNSIGNED NULL,
            organizer_id INT UNSIGNED NULL,
            lost_reason VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX(stage)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS prospect_notes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            prospect_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            kind VARCHAR(10) NOT NULL DEFAULT 'note',
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX(prospect_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    18 => [
        "ALTER TABLE quotes ADD COLUMN session_id INT UNSIGNED NULL",
    ],
    19 => [
        "CREATE TABLE IF NOT EXISTS webhook_endpoints (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            url VARCHAR(500) NOT NULL,
            secret VARCHAR(64) NOT NULL,
            events VARCHAR(500) NOT NULL,
            label VARCHAR(120) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            fail_streak INT NOT NULL DEFAULT 0,
            last_status INT NULL,
            last_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX(organizer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS webhook_deliveries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            endpoint_id INT UNSIGNED NOT NULL,
            event VARCHAR(40) NOT NULL,
            payload MEDIUMTEXT NOT NULL,
            status VARCHAR(8) NOT NULL DEFAULT 'pending',
            http_code INT NULL,
            response VARCHAR(500) NULL,
            attempts INT NOT NULL DEFAULT 0,
            next_try_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            delivered_at DATETIME NULL,
            INDEX(endpoint_id), INDEX(status, next_try_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS bars (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            name VARCHAR(80) NOT NULL,
            pin VARCHAR(8) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS bar_products (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            name VARCHAR(80) NOT NULL,
            category VARCHAR(30) NOT NULL DEFAULT 'boisson',
            price INT NOT NULL,
            deposit INT NOT NULL DEFAULT 0,
            bar_ids VARCHAR(255) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort INT NOT NULL DEFAULT 0,
            INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS wallets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            ticket_id INT UNSIGNED NULL,
            code VARCHAR(40) NOT NULL UNIQUE,
            token VARCHAR(40) NOT NULL,
            email VARCHAR(190) NULL,
            balance INT NOT NULL DEFAULT 0,
            status VARCHAR(10) NOT NULL DEFAULT 'active',
            refund_iban VARCHAR(40) NULL,
            refunded_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX(event_id), INDEX(ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS wallet_tx (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            wallet_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            kind VARCHAR(10) NOT NULL,
            amount INT NOT NULL,
            bar_id INT UNSIGNED NULL,
            items TEXT NULL,
            order_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NULL,
            device VARCHAR(60) NULL,
            undone TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX(wallet_id), INDEX(event_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE events ADD COLUMN cashless_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN cashless_min_topup INT NOT NULL DEFAULT 1000, ADD COLUMN cashless_refund_until DATE NULL",
        "ALTER TABLE order_items MODIFY kind ENUM('rate','product','gift','pass','shop','topup') NOT NULL DEFAULT 'rate'",
        "CREATE TABLE IF NOT EXISTS resellers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            kind VARCHAR(30) NULL,
            contact_name VARCHAR(190) NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(40) NULL,
            city VARCHAR(120) NULL,
            commission_pct DECIMAL(5,2) NOT NULL DEFAULT 5,
            token VARCHAR(40) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS reseller_access (
            reseller_id INT UNSIGNED NOT NULL,
            organizer_id INT UNSIGNED NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            commission_pct DECIMAL(5,2) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (reseller_id, organizer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE orders ADD COLUMN reseller_id INT UNSIGNED NULL, ADD COLUMN reseller_commission INT NOT NULL DEFAULT 0, ADD INDEX (reseller_id)",
    ],
    20 => [
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
    ],
    21 => [
        "CREATE TABLE IF NOT EXISTS pos_devices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            bar_id INT UNSIGNED NULL,
            name VARCHAR(80) NOT NULL,
            pair_code CHAR(6) NULL,
            pair_expires DATETIME NULL,
            token_hash CHAR(64) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            paired_at DATETIME NULL,
            last_seen DATETIME NULL,
            platform VARCHAR(40) NULL,
            revoked TINYINT(1) NOT NULL DEFAULT 0,
            tpe_json TEXT NULL,
            UNIQUE KEY (token_hash), INDEX(event_id), INDEX(pair_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS pos_sales (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            bar_id INT UNSIGNED NOT NULL,
            device_id INT UNSIGNED NULL,
            method ENUM('cashless','card','cash') NOT NULL,
            amount INT NOT NULL,
            items TEXT NULL,
            wallet_id INT UNSIGNED NULL,
            wallet_tx_id INT UNSIGNED NULL,
            cash_given INT NULL,
            payment_ref VARCHAR(255) NULL,
            staff VARCHAR(60) NULL,
            client_id VARCHAR(40) NULL,
            offline TINYINT(1) NOT NULL DEFAULT 0,
            undone TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            received_at DATETIME NOT NULL,
            UNIQUE KEY (client_id), INDEX(event_id, created_at), INDEX(bar_id), INDEX(device_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE wallets ADD COLUMN nfc_uid VARCHAR(40) NULL, ADD UNIQUE KEY ev_nfc (event_id, nfc_uid)",
    ],
];
