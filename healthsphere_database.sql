
-- ============================================================
-- PROJECT: HealthSphere
-- DATABASE: healthsphere_db
-- SERVER: MySQL 8.0+
-- PURPOSE: Complete initial database setup
-- ============================================================

CREATE DATABASE IF NOT EXISTS healthsphere_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE healthsphere_db;

-- ============================================================
-- 1. USERS
-- Stores registration and login information.
-- Passwords must be stored as secure password hashes by PHP.
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    user_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(25) NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    account_status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. HEALTH PROFILES
-- Stores optional profile and health-support preferences.
-- Age groups: child 0-11, adult 12-49, senior 50+.
-- ============================================================

CREATE TABLE IF NOT EXISTS health_profiles (
    profile_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    date_of_birth DATE NOT NULL,
    age_group ENUM('child', 'adult', 'senior') NOT NULL,
    gender VARCHAR(40) NULL,
    blood_group VARCHAR(5) NULL,
    allergies TEXT NULL,
    existing_conditions TEXT NULL,
    accessibility_notes TEXT NULL,
    guardian_name VARCHAR(120) NULL,
    guardian_phone VARCHAR(25) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_profiles_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 3. HEALTH REMINDERS
-- Supports medicine, appointment, water and general reminders.
-- ============================================================

CREATE TABLE IF NOT EXISTS reminders (
    reminder_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    reminder_title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    reminder_type ENUM(
        'medicine',
        'appointment',
        'water',
        'exercise',
        'general'
    ) NOT NULL DEFAULT 'general',
    reminder_datetime DATETIME NOT NULL,
    notification_sent_at DATETIME NULL,
    repeat_type ENUM(
        'none',
        'daily',
        'weekly',
        'monthly'
    ) NOT NULL DEFAULT 'none',
    status ENUM('active', 'completed', 'cancelled')
        NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_reminders_user_datetime (user_id, reminder_datetime),
    INDEX idx_reminders_status (status),

    CONSTRAINT fk_reminders_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 4. EMERGENCY CONTACTS
-- Stores contacts chosen by the registered user.
-- ============================================================

CREATE TABLE IF NOT EXISTS emergency_contacts (
    contact_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    contact_name VARCHAR(120) NOT NULL,
    relationship VARCHAR(60) NULL,
    phone VARCHAR(25) NOT NULL,
    email VARCHAR(190) NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_emergency_contacts_user (user_id),

    CONSTRAINT fk_emergency_contacts_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 5. APPOINTMENTS
-- Stores appointments entered by the user.
-- ============================================================

CREATE TABLE IF NOT EXISTS appointments (
    appointment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    provider_name VARCHAR(150) NULL,
    facility_name VARCHAR(180) NULL,
    appointment_type VARCHAR(100) NULL,
    appointment_datetime DATETIME NOT NULL,
    location VARCHAR(255) NULL,
    notes TEXT NULL,
    status ENUM(
        'scheduled',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_appointments_user_datetime (user_id, appointment_datetime),

    CONSTRAINT fk_appointments_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 6. LAB REQUESTS
-- Records requests entered through the website.
-- This does not automatically book an external laboratory.
-- ============================================================

CREATE TABLE IF NOT EXISTS lab_requests (
    lab_request_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    lab_name VARCHAR(180) NULL,
    test_name VARCHAR(180) NOT NULL,
    preferred_date DATE NULL,
    location VARCHAR(255) NULL,
    notes TEXT NULL,
    status ENUM(
        'requested',
        'contacted',
        'confirmed',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'requested',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_lab_requests_user (user_id),
    INDEX idx_lab_requests_status (status),

    CONSTRAINT fk_lab_requests_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 7. HEALTH SUPPORT CHAT MESSAGES
-- Stores optional chat history for the signed-in user.
-- The chat must not claim to diagnose or prescribe treatment.
-- ============================================================

CREATE TABLE IF NOT EXISTS chat_messages (
    message_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    sender ENUM('user', 'assistant') NOT NULL,
    message_text TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_chat_user_created (user_id, created_at),

    CONSTRAINT fk_chat_messages_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 8. HEALTH ACTIVITY LOG
-- Allows users to record general wellness activities.
-- Do not use this table as a diagnostic record.
-- ============================================================

CREATE TABLE IF NOT EXISTS health_activity_logs (
    activity_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    activity_type ENUM(
        'water',
        'exercise',
        'sleep',
        'wellness',
        'other'
    ) NOT NULL DEFAULT 'other',
    activity_description VARCHAR(255) NOT NULL,
    activity_value DECIMAL(10,2) NULL,
    value_unit VARCHAR(30) NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_activity_user_recorded (user_id, recorded_at),

    CONSTRAINT fk_activity_logs_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 9. USER NOTIFICATIONS AND ACTIVITY HISTORY
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

-- 10. VERIFY THE DATABASE TABLES
-- Expected: 11 application tables.
-- No demo users, appointments, reminders or health records
-- are inserted by this script.
-- ============================================================

SHOW TABLES;

-- Check the structure of the database.
SELECT
    TABLE_NAME,
    ENGINE
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'healthsphere_db'
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;
