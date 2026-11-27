<?php
// ---------- 2.0.0 : modules plus poussés ----------
return [
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
];
