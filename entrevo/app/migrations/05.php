<?php
// ---------- 1.3.0 : staff & bénévoles (préparé), votes pour les modules à venir, objectif de recettes ----------
return [
        "CREATE TABLE IF NOT EXISTS staff_members (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT UNSIGNED NOT NULL,
            first_name VARCHAR(80) NOT NULL,
            last_name VARCHAR(80) NOT NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(40) NULL,
            team VARCHAR(80) NULL,
            skills VARCHAR(255) NULL,
            tshirt VARCHAR(10) NULL,
            notes TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            token VARCHAR(40) NOT NULL,
            created_at DATETIME NOT NULL,
            last_seen_at DATETIME NULL,
            UNIQUE KEY (token), INDEX(organizer_id, status), INDEX(organizer_id, email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS staff_shifts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            team VARCHAR(80) NULL,
            title VARCHAR(120) NOT NULL,
            location VARCHAR(120) NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            needed INT NOT NULL DEFAULT 1,
            description TEXT NULL,
            open_signup TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            INDEX(event_id, starts_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS staff_assignments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shift_id INT UNSIGNED NOT NULL,
            member_id INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'proposed',
            checked_in_at DATETIME NULL,
            checked_out_at DATETIME NULL,
            reminder_sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY (shift_id, member_id), INDEX(member_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE events
            ADD COLUMN volunteer_recruit TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN volunteer_intro TEXT NULL",

        "CREATE TABLE IF NOT EXISTS feature_votes (
            organizer_id INT UNSIGNED NOT NULL,
            feature VARCHAR(40) NOT NULL,
            user_id INT UNSIGNED NULL,
            comment VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (organizer_id, feature)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE events
            ADD COLUMN revenue_goal INT NULL",
];
