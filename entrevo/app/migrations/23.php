<?php
return [
        "ALTER TABLE bar_products ADD COLUMN kind VARCHAR(10) NOT NULL DEFAULT 'item', ADD COLUMN options_json TEXT NULL, ADD COLUMN menu_json TEXT NULL, ADD COLUMN prep TINYINT(1) NULL",
        "ALTER TABLE pos_categories ADD COLUMN prep TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE pos_sales ADD COLUMN kind VARCHAR(10) NOT NULL DEFAULT 'sale', ADD COLUMN payments_json VARCHAR(1000) NULL, ADD COLUMN order_no INT NULL,
            ADD COLUMN prep_status VARCHAR(10) NULL, ADD COLUMN ready_at DATETIME NULL, ADD COLUMN served_at DATETIME NULL, ADD INDEX ev_prep (event_id, prep_status)",
        "ALTER TABLE bars ADD COLUMN order_seq INT NOT NULL DEFAULT 0, ADD COLUMN order_day DATE NULL",
];
