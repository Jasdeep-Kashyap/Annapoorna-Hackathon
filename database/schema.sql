CREATE DATABASE IF NOT EXISTS Annapoorna;
USE Annapoorna;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role` ENUM('donor','ngo','recycler','admin') NOT NULL,
  `org_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `lat` DECIMAL(10,7) NULL,
  `lng` DECIMAL(10,7) NULL,
  `verification_doc_path` VARCHAR(255) NULL,
  `approval_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `trust_score` DECIMAL(4,1) DEFAULT 100.0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `chk_trust_score` CHECK (`trust_score` BETWEEN 0.0 AND 100.0),
  INDEX `idx_role` (`role`)
);

CREATE TABLE `food_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `donor_selectable` BOOLEAN DEFAULT TRUE,
  `default_expiry_minutes` INT NOT NULL,
  `radius_expand_minutes` INT NOT NULL
);

-- Seed Categories
INSERT INTO `food_categories` (`name`, `donor_selectable`, `default_expiry_minutes`, `radius_expand_minutes`) VALUES
('Cooked rice & grains', 1, 240, 45),
('Breads & baked items', 1, 360, 60),
('Sealed packaged goods', 1, 1440, 120),
('Cooked vegetarian curries / meals', 1, 180, 45),
('Raw meat / poultry', 0, 0, 0),
('Dairy items requiring cold-chain', 0, 0, 0),
('Cut fruits & fresh salads', 0, 0, 0);

-- Seed Admin User (password: admin123)
INSERT INTO `users` (`role`, `org_name`, `email`, `password_hash`, `approval_status`) VALUES
('admin', 'Annapoorna Admin', 'admin@annapoorna.org', '$2y$10$2DKVaA6hl9mt1lEDemixAej7SjvM6tXnmLXwwJiv/y.NgCpZWd.R6', 'approved');

CREATE TABLE `listings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `donor_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `portions_posted` INT NOT NULL,
  `portions_verified` INT NULL,
  `photo_path` VARCHAR(255) NULL,
  `cooked_at` DATETIME NOT NULL,
  `expiry_at` DATETIME NOT NULL,
  `lat` DECIMAL(10,7) NOT NULL,
  `lng` DECIMAL(10,7) NOT NULL,
  `status` ENUM('AVAILABLE','RESERVED','IN_TRANSIT','COMPLETED','EXPIRED_SPOILED','RECYCLE_CLAIMED','RECYCLED') DEFAULT 'AVAILABLE',
  `radius_km` DECIMAL(3,1) DEFAULT 5.0,
  `otp` CHAR(4) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`donor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `food_categories`(`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_portions` CHECK (`portions_posted` > 0),
  INDEX `idx_status_expiry` (`status`, `expiry_at`)
);

CREATE TABLE `claims` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `listing_id` INT NOT NULL,
  `claimant_id` INT NOT NULL,
  `claimant_role` ENUM('ngo','recycler') NOT NULL,
  `claimed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `otp_verified_at` TIMESTAMP NULL,
  `checklist_odor_ok` BOOLEAN NULL,
  `checklist_visual_ok` BOOLEAN NULL,
  `checklist_storage_ok` BOOLEAN NULL,
  `verification_photo_path` VARCHAR(255) NULL,
  `completed_at` TIMESTAMP NULL,
  `bulk_weight_kg` DECIMAL(6,2) NULL,
  `no_show_reported` BOOLEAN DEFAULT FALSE,
  UNIQUE `unique_listing_claim` (`listing_id`),
  FOREIGN KEY (`listing_id`) REFERENCES `listings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`claimant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
