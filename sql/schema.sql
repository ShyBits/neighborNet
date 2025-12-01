-- NeighborNet Datenbank Schema

CREATE DATABASE IF NOT EXISTS `neighbornet` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `neighbornet`;

-- Tabelle: users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) DEFAULT NULL,
    `last_name` VARCHAR(100) DEFAULT NULL,
    `street` VARCHAR(255) DEFAULT NULL,
    `house_number` VARCHAR(20) DEFAULT NULL,
    `postcode` VARCHAR(20) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: angebote
CREATE TABLE IF NOT EXISTS `angebote` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `address` VARCHAR(500) NOT NULL,
    `lat` DECIMAL(10, 8) NOT NULL,
    `lng` DECIMAL(11, 8) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: angebote_images
CREATE TABLE IF NOT EXISTS `angebote_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `angebot_id` INT NOT NULL,
    `image_path` VARCHAR(500) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`angebot_id`) REFERENCES `angebote`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: messages
CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `chat_id` INT NULL,
    `sender_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `message` LONGTEXT NOT NULL,
    `encrypted` TINYINT(1) DEFAULT 0,
    `file_path` VARCHAR(500) NULL,
    `file_type` VARCHAR(20) NULL,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_chat_id` (`chat_id`),
    INDEX `idx_sender_receiver` (`sender_id`, `receiver_id`, `created_at`),
    INDEX `idx_receiver_sender` (`receiver_id`, `sender_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: anfragen (Anfragen auf Angebote)
CREATE TABLE IF NOT EXISTS `anfragen` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `angebot_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `message` TEXT DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'pending',
    `confirmed_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_by_helper` TIMESTAMP NULL DEFAULT NULL,
    `completed_by_requester` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`angebot_id`) REFERENCES `angebote`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_anfrage` (`angebot_id`, `user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_angebot_status` (`angebot_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: chats
CREATE TABLE IF NOT EXISTS `chats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: chat_participants
CREATE TABLE IF NOT EXISTS `chat_participants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `chat_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `last_read_message_id` INT DEFAULT NULL,
    `last_read_at` TIMESTAMP NULL DEFAULT NULL,
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_participant` (`chat_id`, `user_id`),
    FOREIGN KEY (`chat_id`) REFERENCES `chats`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_chat` (`user_id`, `chat_id`),
    INDEX `idx_chat_user` (`chat_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: chat_metadata
CREATE TABLE IF NOT EXISTS `chat_metadata` (
    `chat_id` INT NOT NULL PRIMARY KEY,
    `last_message_id` INT NULL,
    `last_message_at` TIMESTAMP NULL,
    `unread_count_user_1` INT DEFAULT 0,
    `unread_count_user_2` INT DEFAULT 0,
    FOREIGN KEY (`chat_id`) REFERENCES `chats`(`id`) ON DELETE CASCADE,
    INDEX `idx_last_message_at` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle: chat_requests
CREATE TABLE IF NOT EXISTS `chat_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `anfrage_id` INT NOT NULL,
    `chat_id` INT NULL,
    `requester_id` INT NOT NULL,
    `helper_id` INT NOT NULL,
    `status` VARCHAR(20) DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_anfrage_chat` (`anfrage_id`),
    FOREIGN KEY (`anfrage_id`) REFERENCES `anfragen`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`chat_id`) REFERENCES `chats`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`requester_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`helper_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

