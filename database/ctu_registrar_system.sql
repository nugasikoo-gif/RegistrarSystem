-- ============================================================
-- CTU REGISTRAR DIGITAL QUEUE & DOCUMENT REQUEST SYSTEM
-- Database: ctu_registrar_system
-- Version: 1.0
-- ============================================================

-- Create the database
CREATE DATABASE IF NOT EXISTS `ctu_registrar_system`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `ctu_registrar_system`;

-- ============================================================
-- TABLE 1: users
-- Stores all user accounts (students, staff, admin)
-- ============================================================
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` VARCHAR(20) DEFAULT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('student', 'staff', 'admin') NOT NULL DEFAULT 'student',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_email` (`email`),
  UNIQUE KEY `uk_student_id` (`student_id`)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 2: document_types
-- List of documents students can request
-- Admin can add, edit, or deactivate these
-- ============================================================
CREATE TABLE `document_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `document_name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 3: document_requests
-- Stores every document request submitted by students
-- ============================================================
CREATE TABLE `document_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `document_type_id` INT NOT NULL,
  `tracking_number` VARCHAR(20) NOT NULL,
  `purpose` TEXT NOT NULL,
  `status` ENUM('Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected') NOT NULL DEFAULT 'Pending',
  `remarks` TEXT DEFAULT NULL,
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_tracking` (`tracking_number`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`document_type_id`) REFERENCES `document_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 4: queues
-- Stores daily queue entries for registrar visits
-- ============================================================
CREATE TABLE `queues` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `service_type` VARCHAR(50) NOT NULL,
  `queue_number` VARCHAR(10) NOT NULL,
  `queue_date` DATE NOT NULL,
  `status` ENUM('Waiting', 'Serving', 'Completed', 'Skipped') NOT NULL DEFAULT 'Waiting',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `called_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_queue_date` (`queue_date`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 5: notifications
-- In-app notifications for students
-- ============================================================
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Sample Users
-- Passwords: admin123, staff123, student123 (all hashed with bcrypt)
INSERT INTO `users` (`student_id`, `full_name`, `email`, `password`, `role`) VALUES
(NULL,        'System Administrator', 'admin@ctu.edu.ph',           '$2y$10$GOvK/KDQBlwbAHREMoHaE.bLlZTKCt3r7aKRom.bmlq1cmzbHCQsG', 'admin'),
(NULL,        'Maria Santos',         'staff@ctu.edu.ph',           '$2y$10$gbBbYMelhkovkLneyHnwV.J2RF.LERUaw86PClMNz86hkfb2P4NJK', 'staff'),
('2024-0001', 'Juan Dela Cruz',       'juan@student.ctu.edu.ph',    '$2y$10$CIL.VdXc/7xLDkOKCQ/azulRRdeStV28xXx3oZp5aUQvWWKH1f9I6', 'student');

-- Sample Document Types
INSERT INTO `document_types` (`document_name`, `description`, `is_active`) VALUES
('Transcript of Records (TOR)',   'Official record of all courses taken and grades received.',           1),
('Certificate of Enrollment',    'Certifies that the student is currently enrolled.',                    1),
('Certificate of Grades',        'Official certificate showing grades for a specific semester.',         1),
('Good Moral Certificate',       'Certificate of good moral character.',                                1),
('Certification of Graduation',  'Certifies that the student has completed their degree program.',      1);

