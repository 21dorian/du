<?php
return [
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
];
