-- ============================================================
-- migration_clock.sql
-- Clock In/Out feature – Database setup
-- Run this once in phpMyAdmin or MySQL CLI:
--   SOURCE /path/to/Backend/Database/migration_clock.sql;
-- ============================================================

-- Ensure time_entries table exists (safe: won't drop existing data)
CREATE TABLE IF NOT EXISTS `time_entries` (
    `id`         INT(11)   NOT NULL AUTO_INCREMENT,
    `user_id`    INT(11)   NOT NULL,
    `project_id` INT(11)   NOT NULL,
    `clock_in`   DATETIME  NOT NULL,
    `clock_out`  DATETIME  DEFAULT NULL,
    `date`       DATE      NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_open` (`user_id`, `clock_out`),
    CONSTRAINT `fk_te_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_te_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Enable the MySQL Event Scheduler ──────────────────────────────────────────
SET GLOBAL event_scheduler = ON;

-- ── Auto clock-out after 8 hours ─────────────────────────────────────────────
-- Drops and re-creates so re-running this script is safe
DROP EVENT IF EXISTS `auto_clockout_8h`;

CREATE EVENT `auto_clockout_8h`
    ON SCHEDULE EVERY 5 MINUTE
    COMMENT 'Force clock-out any user who has been clocked in for more than 8 hours'
    DO
        UPDATE `time_entries`
           SET `clock_out` = NOW()
         WHERE `clock_out` IS NULL
           AND `clock_in` <= NOW() - INTERVAL 8 HOUR;
