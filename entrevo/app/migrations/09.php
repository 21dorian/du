<?php
// ---------- 1.8.0 : donner son billet, agenda & rappels, paniers abandonnés, avis, early bird ----------
return [
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
];
