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
];
