<?php
return [
        "CREATE TABLE IF NOT EXISTS pos_tabs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            bar_id INT UNSIGNED NOT NULL,
            label VARCHAR(80) NOT NULL,
            cart_json TEXT NOT NULL,
            total INT NOT NULL DEFAULT 0,
            items INT NOT NULL DEFAULT 0,
            version INT NOT NULL DEFAULT 1,
            status VARCHAR(10) NOT NULL DEFAULT 'open',
            staff_id INT UNSIGNED NULL,
            staff VARCHAR(60) NULL,
            device_id INT UNSIGNED NULL,
            sale_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            closed_at DATETIME NULL,
            INDEX(event_id, bar_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE pos_sessions ADD COLUMN float_json VARCHAR(600) NULL, ADD COLUMN count_json VARCHAR(600) NULL, ADD COLUMN validated_at DATETIME NULL, ADD COLUMN validated_by INT UNSIGNED NULL, ADD COLUMN validation_note VARCHAR(500) NULL",
        "ALTER TABLE pos_cash_moves ADD COLUMN count_json VARCHAR(600) NULL",
        "ALTER TABLE pos_sales ADD COLUMN tab_id INT UNSIGNED NULL",
];
