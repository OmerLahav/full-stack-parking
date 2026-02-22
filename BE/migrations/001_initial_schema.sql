-- Smart Parking - Initial Schema
-- Run this migration to create the database structure

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parking_spots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    spot_number INT UNSIGNED NOT NULL UNIQUE,
    floor_number INT UNSIGNED DEFAULT 1,
    type ENUM('Regular', 'Handicap') DEFAULT 'Regular',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_spot_number (spot_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    spot_id INT UNSIGNED NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('Booked', 'Completed') DEFAULT 'Booked',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (spot_id) REFERENCES parking_spots(id) ON DELETE CASCADE,
    INDEX idx_spot_time (spot_id, start_time, end_time),
    INDEX idx_status_end_time (status, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
