<?php
return [
        "ALTER TABLE bar_products ADD COLUMN cost INT NULL",
        "ALTER TABLE pos_sales ADD COLUMN tip INT NOT NULL DEFAULT 0, ADD COLUMN refund_of INT UNSIGNED NULL, ADD INDEX(refund_of)",
        "CREATE TABLE IF NOT EXISTS pos_stock_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            bar_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            qty INT NOT NULL,
            note VARCHAR(255) NULL,
            staff VARCHAR(60) NULL,
            device_id INT UNSIGNED NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'open',
            done_qty INT NULL,
            done_by INT UNSIGNED NULL,
            done_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX(event_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
