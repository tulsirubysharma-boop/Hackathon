-- Run once on an existing HealthSphere database. This preserves existing records.
USE healthsphere_db;

-- Reminder browser notifications are delivered while the dashboard is open.
ALTER TABLE reminders
    ADD COLUMN notification_sent_at DATETIME NULL AFTER reminder_datetime;

CREATE TABLE IF NOT EXISTS user_notifications (
    notification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    notification_type ENUM('email', 'system') NOT NULL DEFAULT 'system',
    subject VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    email_status ENUM('not_sent', 'pending', 'sent', 'failed') NOT NULL DEFAULT 'not_sent',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    INDEX idx_user_notifications_user_created (user_id, created_at),
    CONSTRAINT fk_user_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_activity_history (
    history_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_activity_history_user_time (user_id, occurred_at),
    CONSTRAINT fk_user_activity_history_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demo_medicine_orders (
    order_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    item_description VARCHAR(180) NOT NULL,
    status ENUM('demo_only') NOT NULL DEFAULT 'demo_only',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_demo_medicine_orders_user (user_id, created_at),
    CONSTRAINT fk_demo_medicine_orders_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;
