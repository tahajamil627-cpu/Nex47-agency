-- =========================================================================
-- NEX - 47 CATALYS'S Database Schema
-- Database Name: nex47_db
-- =========================================================================

CREATE DATABASE IF NOT EXISTS `nex47_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nex47_db`;

-- 1. Leads Table (stores client inquiries from modal and contact form)
CREATE TABLE IF NOT EXISTS `leads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_name` VARCHAR(150) NOT NULL,
    `client_phone` VARCHAR(50) NOT NULL,
    `brand_name` VARCHAR(150) DEFAULT NULL,
    `service_type` VARCHAR(150) NOT NULL,
    `budget` VARCHAR(100) DEFAULT '$1,000 - $3,000',
    `message` TEXT DEFAULT NULL,
    `source` VARCHAR(50) DEFAULT 'Website Modal / Form',
    `status` ENUM('new', 'contacted', 'in_progress', 'converted', 'closed') DEFAULT 'new',
    `notes` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Services Master Table
CREATE TABLE IF NOT EXISTS `services` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `service_number` VARCHAR(10) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `tagline` VARCHAR(255) DEFAULT NULL,
    `description` TEXT NOT NULL,
    `sub_features` JSON DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Case Studies Table
CREATE TABLE IF NOT EXISTS `case_studies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `duration` VARCHAR(50) NOT NULL,
    `roas_metric` VARCHAR(50) NOT NULL,
    `revenue_metric` VARCHAR(50) NOT NULL,
    `summary` TEXT NOT NULL,
    `is_featured` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Admin Users Table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) UNIQUE NOT NULL,
    `role` ENUM('superadmin', 'manager', 'analyst') DEFAULT 'superadmin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Activity Logs Table
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default admin (Password: admin123)
INSERT INTO `admins` (`username`, `password_hash`, `full_name`, `email`, `role`)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'NEX-47 Lead Director', 'admin@nex47.com', 'superadmin')
ON DUPLICATE KEY UPDATE `full_name`=VALUES(`full_name`);

-- Seed initial sample demo leads
INSERT INTO `leads` (`client_name`, `client_phone`, `brand_name`, `service_type`, `budget`, `message`, `status`)
VALUES 
('Zubair Ahmed', '+923001234567', 'Aura Luxury Apparel', 'Meta Ads / Facebook Ads', '$3,000 - $7,500', 'Looking to scale ROAS from 2.5x to 5x for nationwide e-commerce.', 'new'),
('Hamza Tariq', '+923219876543', 'Apex Real Estate', 'Lead Generation & Funnels', '$7,500 - $20,000', 'Need WhatsApp lead generation funnel for DHA Phase 9 plots.', 'contacted'),
('Sara Mansoor', '+923334445566', 'Glow Skin Studio', 'Social Media Marketing (SMM)', '$1,000 - $3,000', 'Need viral reels production and TikTok management.', 'in_progress')
ON DUPLICATE KEY UPDATE `client_name`=VALUES(`client_name`);
