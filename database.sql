-- =====================================================================
-- Technical Discussion Forum - Database Schema & Seed Data
-- Compatible with MySQL 8.0+ and TiDB Cloud (MySQL-Compatible)
-- BCA College Assignment Project
-- =====================================================================

-- Set character encoding & timezone defaults
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1. Create database if it does not already exist
CREATE DATABASE IF NOT EXISTS `forum_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `forum_db`;

-- ---------------------------------------------------------------------
-- Table: users
-- Stores all registered users, administrators, and moderators.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('user', 'moderator', 'admin') NOT NULL DEFAULT 'user',
    `is_banned` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_banned` (`is_banned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: categories
-- Discussion categories created and managed by administrators.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: threads
-- Technical discussion topics created by users within categories.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `threads` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `body` MEDIUMTEXT NOT NULL,
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
    `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
    `views` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_threads_category` (`category_id`),
    INDEX `idx_threads_user` (`user_id`),
    INDEX `idx_threads_pinned_created` (`is_pinned`, `created_at` DESC),
    CONSTRAINT `fk_threads_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_threads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: replies
-- Flat responses to threads. Author can mark one as "is_best".
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `replies` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `thread_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `body` TEXT NOT NULL,
    `is_best` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_replies_thread` (`thread_id`),
    INDEX `idx_replies_user` (`user_id`),
    INDEX `idx_replies_best` (`is_best`),
    CONSTRAINT `fk_replies_thread` FOREIGN KEY (`thread_id`) REFERENCES `threads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_replies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: votes
-- Single upvote (+1) or downvote (-1) per user on threads or replies.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `votes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `target_type` ENUM('thread', 'reply') NOT NULL,
    `target_id` INT UNSIGNED NOT NULL,
    `value` TINYINT NOT NULL COMMENT '1 = upvote, -1 = downvote',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_target_vote` (`user_id`, `target_type`, `target_id`),
    INDEX `idx_votes_target` (`target_type`, `target_id`),
    CONSTRAINT `fk_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: login_attempts
-- Tracks failed login attempts for brute-force rate limiting (5 attempts / 10 mins).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(191) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_login_rate_limit` (`email`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA
-- Default accounts:
-- 1. admin@example.com  / Admin@123 (role: admin)
-- 2. mod@example.com    / Mod@123   (role: moderator)
-- 3. alice@example.com  / User@123  (role: user)
-- 4. bob@example.com    / User@123  (role: user)
-- =====================================================================

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `is_banned`, `created_at`) VALUES
(1, 'Dark', 'dark@example.com', '$2y$10$cxAyW1kiX.sWuyVZcOrz6.5Z9FuRmCKIf0.EeZQ9QEDyGf9wkeXdS', 'admin', 0, NOW()),
(2, 'Deepak Verma', 'deepak@example.com', '$2y$10$758wjr7HIn8gQWIg6VydQ.4X0bxxWONaYP1DaXl/vAt9x3A0iDtEG', 'moderator', 0, NOW()),
(3, 'Ananya Sen', 'ananya@example.com', '$2y$10$t5U9kfiEy72lst4FgLZB5uXOmWlHXmK5sEKUx4DpKnl9BQnuH8Phi', 'user', 0, NOW()),
(4, 'Bhavin Patel', 'bhavin@example.com', '$2y$10$t5U9kfiEy72lst4FgLZB5uXOmWlHXmK5sEKUx4DpKnl9BQnuH8Phi', 'user', 0, NOW())
ON DUPLICATE KEY UPDATE `email` = `email`;

-- 5 Technical Categories
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Coding', 'General programming discussions, syntax, data structures, algorithms, and debugging help across languages.'),
(2, 'AI & Machine Learning', 'Discussions on artificial intelligence, neural networks, LLMs, prompt engineering, and data science.'),
(3, 'Web Development', 'Frontend, backend, HTML5, CSS3, JavaScript, PHP 8+, RESTful APIs, and full-stack web engineering.'),
(4, 'Databases', 'Relational SQL, TiDB Cloud, MySQL, indexing, query optimization, normalization, and distributed storage.'),
(5, 'Career', 'Software engineering projects, tech internships, resume reviews, technical interview prep, and industry advice.')
ON DUPLICATE KEY UPDATE `name` = `name`;

-- Sample Threads
INSERT INTO `threads` (`id`, `category_id`, `user_id`, `title`, `body`, `is_pinned`, `is_locked`, `views`, `created_at`) VALUES
(1, 1, 1, 'Welcome to the Technical Discussion Forum! [Community Guidelines]', 'Hello everyone!\n\nWelcome to ThredIt Technical Discussion Forum. This platform is built for developers to discuss code, debug issues, explore AI innovations, and share web development projects.\n\n### Community Rules:\n1. Be respectful and constructive in every discussion.\n2. When asking coding questions, share the error message, the expected output, and a minimal reproducible snippet.\n3. Format code cleanly and avoid sharing plaintext passwords or API keys.\n4. Search before posting to see if your question has already been answered.\n\nHappy coding!', 1, 0, 142, NOW()),

(2, 4, 3, 'How does TiDB Cloud achieve horizontal scalability while maintaining MySQL compatibility?', 'I have been studying distributed databases and came across TiDB Cloud.\n\nTraditional MySQL typically uses primary-replica replication where only one node handles writes. How does TiDB separate compute from storage to support distributed multi-node writes while still letting us connect with standard MySQL PDO drivers?', 0, 0, 68, NOW()),

(3, 3, 4, 'Why is PDO preferred over mysqli in modern PHP 8+ web applications?', 'I am building our web application project and evaluating both `mysqli` and `PDO`.\n\nWhat are the architectural advantages of choosing PDO over mysqli? Does PDO make it easier to switch between local MySQL and cloud databases like TiDB?', 0, 0, 89, NOW()),

(4, 2, 3, 'How are LLMs and AI coding assistants changing the way junior developers learn programming?', 'AI tools are becoming very popular for autocomplete, debugging, and code explanation.\n\nHow should junior developers approach learning fundamentals like data structures and problem solving without becoming overly reliant on auto-generated code?', 0, 0, 45, NOW()),

(5, 3, 2, '[Archived] Legacy PHP 5.4 mysql_* extension migration discussion', 'This is an archived reference discussion on migrating legacy PHP code from deprecated mysql_query functions to modern parameterized PDO queries. Thread is locked for historical reference.', 0, 1, 31, NOW())
ON DUPLICATE KEY UPDATE `title` = `title`;

-- Sample Replies
INSERT INTO `replies` (`id`, `thread_id`, `user_id`, `body`, `is_best`, `created_at`) VALUES
(1, 2, 1, 'Great question Alice!\n\nTiDB uses a two-layer architecture:\n1. **TiDB Server Layer (Compute)**: Stateless nodes that receive standard MySQL SQL queries, parse them, optimize the execution plan, and route requests. Because it is stateless, you can scale this layer horizontally.\n2. **TiKV Layer (Storage)**: A distributed transactional Key-Value engine using the Raft consensus protocol. Data is split into continuous key ranges called Regions.\n\nBecause the protocol layer speaks the standard MySQL wire protocol, your PHP application connects via standard `PDO` without needing any proprietary client libraries!', 1, NOW()),

(2, 2, 4, 'To add to the Admin answer: TiDB also supports distributed ACID transactions using a two-phase commit protocol based on Google Percolator. Super useful for cloud-native apps!', 0, NOW()),

(3, 3, 1, 'Here are the key reasons PDO is preferred in modern production PHP:\n\n1. **Database Agnostic**: PDO works with 12+ database drivers (MySQL, PostgreSQL, SQLite, TiDB) with a unified API. mysqli only works with MySQL.\n2. **Named Parameters**: PDO supports `:id`, `:email` placeholders which improve readability compared to just `?`.\n3. **Object Mapping**: PDO can fetch directly into classes or custom objects with `PDO::FETCH_CLASS`.\n4. **Consistent Exception Handling**: `PDO::ERRMODE_EXCEPTION` standardizes error catching across all database operations.', 1, NOW()),

(4, 3, 2, 'Also, in our forum codebase, PDO enables switching between local XAMPP MySQL (port 3306) and TiDB Cloud (port 4000 with SSL) just by editing the `.env` file without changing a single line of application code!', 0, NOW())
ON DUPLICATE KEY UPDATE `body` = `body`;

-- Sample Votes
INSERT INTO `votes` (`id`, `user_id`, `target_type`, `target_id`, `value`, `created_at`) VALUES
(1, 1, 'thread', 2, 1, NOW()),
(2, 2, 'thread', 2, 1, NOW()),
(3, 4, 'thread', 2, 1, NOW()),
(4, 3, 'thread', 3, 1, NOW()),
(5, 2, 'thread', 3, 1, NOW()),
(6, 4, 'reply', 1, 1, NOW()),
(7, 3, 'reply', 1, 1, NOW()),
(8, 2, 'reply', 3, 1, NOW()),
(9, 3, 'reply', 3, 1, NOW())
ON DUPLICATE KEY UPDATE `value` = `value`;
