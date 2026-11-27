<?php
// ---------- 1.5.0 : KYC, factures de commission, TVA, budget, tâches, documents, jauges de zone ----------
return [
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
];
