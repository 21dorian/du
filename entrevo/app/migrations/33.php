<?php
return [
        "ALTER TABLE rates ADD COLUMN extra_fee INT NOT NULL DEFAULT 0, ADD COLUMN extra_fee_label VARCHAR(60) NULL",
        "ALTER TABLE order_items MODIFY kind ENUM('rate','product','gift','pass','shop','topup','fee') NOT NULL DEFAULT 'rate'",
        "ALTER TABLE promos MODIFY kind ENUM('percent','amount','unlock','grid') NOT NULL DEFAULT 'percent', ADD COLUMN grid_json TEXT NULL",
        "ALTER TABLE events ADD COLUMN overline VARCHAR(120) NULL, ADD COLUMN subtitle VARCHAR(190) NULL, ADD COLUMN show_ht TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN reservations TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN reservation_days INT NOT NULL DEFAULT 7, ADD COLUMN reservation_text VARCHAR(500) NULL",
        "ALTER TABLE organizers ADD COLUMN order_copy_email VARCHAR(190) NULL, ADD COLUMN slack_webhook VARCHAR(300) NULL",
        "ALTER TABLE form_fields ADD COLUMN help VARCHAR(300) NULL, ADD COLUMN cond_field INT UNSIGNED NULL, ADD COLUMN cond_value VARCHAR(190) NULL, ADD COLUMN default_on TINYINT(1) NOT NULL DEFAULT 0",
];
