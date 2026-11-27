<?php
return [
        "ALTER TABLE pos_devices ADD COLUMN staff_name VARCHAR(60) NULL, ADD COLUMN station_at DATETIME NULL",
        "ALTER TABLE pos_sessions ADD INDEX bar_open (bar_id, closed_at)",
];
