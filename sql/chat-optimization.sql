-- Optimierte Chat-Datenbankstruktur für NeighborNet
-- Diese Migration optimiert die Chat-Funktionalität mit besseren Tabellen und Indizes

-- 1. Chats-Tabelle für bessere Organisation
CREATE TABLE IF NOT EXISTS `chats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Chat-Participants für viele-zu-viele Beziehung
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

-- 3. Optimierte Messages-Tabelle mit Indizes
-- Hinweis: Wir behalten die aktuelle Struktur, aber fügen Indizes hinzu
ALTER TABLE `messages` 
ADD COLUMN IF NOT EXISTS `chat_id` INT NULL AFTER `id`,
ADD COLUMN IF NOT EXISTS `read_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`,
ADD COLUMN IF NOT EXISTS `edited_at` TIMESTAMP NULL DEFAULT NULL AFTER `read_at`,
ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `edited_at`,
ADD INDEX IF NOT EXISTS `idx_sender_receiver` (`sender_id`, `receiver_id`, `created_at`),
ADD INDEX IF NOT EXISTS `idx_receiver_sender` (`receiver_id`, `sender_id`, `created_at`),
ADD INDEX IF NOT EXISTS `idx_created_at` (`created_at`),
ADD INDEX IF NOT EXISTS `idx_read_at` (`read_at`),
ADD INDEX IF NOT EXISTS `idx_chat_id` (`chat_id`);

-- 4. Chat-Metadata für Quick-Access
CREATE TABLE IF NOT EXISTS `chat_metadata` (
    `chat_id` INT NOT NULL PRIMARY KEY,
    `last_message_id` INT NULL,
    `last_message_at` TIMESTAMP NULL,
    `unread_count_user_1` INT DEFAULT 0,
    `unread_count_user_2` INT DEFAULT 0,
    FOREIGN KEY (`chat_id`) REFERENCES `chats`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`last_message_id`) REFERENCES `messages`(`id`) ON DELETE SET NULL,
    INDEX `idx_last_message_at` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

