<?php
return [
        "ALTER TABLE rates ADD COLUMN dyn_json TEXT NULL",
        "CREATE TABLE IF NOT EXISTS rate_prices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rate_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            old_price INT NOT NULL,
            price INT NOT NULL,
            reason VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            INDEX(rate_id), INDEX(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE organizers ADD COLUMN loyalty_json TEXT NULL",
];
