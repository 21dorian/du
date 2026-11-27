<?php
// ---------- 1.6.0 : intérêt du public pour les fonctions à venir ----------
return [
        "CREATE TABLE IF NOT EXISTS public_interest (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            feature VARCHAR(40) NOT NULL,
            email VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            ip_hash CHAR(16) NULL,
            UNIQUE KEY (feature, email), INDEX(feature)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
