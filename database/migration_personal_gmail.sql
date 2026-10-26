-- Safe migration for Personal Gmail and Email Notifications tracking
-- Preserves all existing tables, foreign keys, records, and passwords

USE `ctu_registrar_system`;

-- Add personal_gmail to users table if not exists
ALTER TABLE `users` 
  ADD COLUMN IF NOT EXISTS `personal_gmail` VARCHAR(255) NULL AFTER `email`;

-- Add last_notified_status to document_requests to prevent duplicate email alerts
ALTER TABLE `document_requests` 
  ADD COLUMN IF NOT EXISTS `last_notified_status` VARCHAR(50) NULL DEFAULT NULL AFTER `remarks`;

