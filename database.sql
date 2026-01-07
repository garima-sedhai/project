-- ============================================
-- ONLINE BILLING SYSTEM DATABASE
-- Created for BCA 4th Semester Project
-- ============================================

-- Create database
DROP DATABASE IF EXISTS `online_billing_system`;
CREATE DATABASE `online_billing_system`
DEFAULT CHARACTER SET utf8mb4 
DEFAULT COLLATE utf8mb4_unicode_ci;

USE `online_billing_system`;

-- ============================================
-- TABLE: users
-- Main user table for customers and admin
-- ============================================
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `address` TEXT,
  `phone` VARCHAR(20),
  `user_type` ENUM('customer', 'admin', 'staff') DEFAULT 'customer',
  `profile_image` VARCHAR(255) DEFAULT NULL,
  `account_status` ENUM('pending', 'active', 'inactive', 'suspended') DEFAULT 'pending',
  `email_verified` TINYINT(1) DEFAULT 0,
  `verification_token` VARCHAR(100) DEFAULT NULL,
  `reset_token` VARCHAR(100) DEFAULT NULL,
  `reset_token_expiry` DATETIME DEFAULT NULL,
  `approved_by_admin` TINYINT(1) DEFAULT 0,
  `approved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: categories
-- Product categories
-- ============================================
CREATE TABLE `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: products
-- Products for billing
-- ============================================
CREATE TABLE `products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `price` DECIMAL(10,2) NOT NULL,
  `category_id` INT(11) NOT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `stock_quantity` INT(11) DEFAULT 0,
  `status` ENUM('available', 'out_of_stock', 'discontinued') DEFAULT 'available',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: bills
-- Customer bills/invoices
-- ============================================
CREATE TABLE `bills` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `bill_number` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT(11) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `discount` DECIMAL(10,2) DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
  `final_amount` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('cash', 'credit_card', 'debit_card', 'online', 'bank_transfer') DEFAULT 'cash',
  `payment_status` ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
  `status` ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
  `notes` TEXT,
  `billing_address` TEXT,
  `due_date` DATE,
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: bill_items
-- Items in each bill
-- ============================================
CREATE TABLE `bill_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `bill_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: payments
-- Payment records
-- ============================================
CREATE TABLE `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `bill_id` INT(11) NOT NULL,
  `transaction_id` VARCHAR(100) NOT NULL UNIQUE,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `payment_status` ENUM('success', 'failed', 'pending', 'refunded') DEFAULT 'pending',
  `payer_email` VARCHAR(100),
  `payer_name` VARCHAR(100),
  `payment_details` TEXT,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: email_verifications
-- OTP verification for registration
-- ============================================
CREATE TABLE `email_verifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(100) NOT NULL,
  `otp_code` VARCHAR(10) NOT NULL,
  `token` VARCHAR(100) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `verified` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: notifications
-- System notifications
-- ============================================
CREATE TABLE `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'error', 'payment', 'bill', 'registration') DEFAULT 'info',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: admin_notifications
-- Admin-specific notifications
-- ============================================
CREATE TABLE `admin_notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('new_user', 'payment', 'bill', 'system') DEFAULT 'system',
  `is_read` TINYINT(1) DEFAULT 0,
  `reference_id` INT(11) DEFAULT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: audit_logs
-- System audit logs
-- ============================================
CREATE TABLE `audit_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: pending_verifications
-- Pending user verifications for admin approval
-- ============================================
CREATE TABLE `pending_verifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `request_type` ENUM('registration', 'update') DEFAULT 'registration',
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `admin_notes` TEXT,
  `verified_at` DATETIME DEFAULT NULL,
  `verified_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- DEFAULT DATA INSERTION
-- ============================================

-- Insert default admin user (email: admin@billpay.com, password: password)
INSERT INTO `users` (
  `username`, 
  `email`, 
  `password`, 
  `full_name`, 
  `user_type`, 
  `account_status`, 
  `email_verified`,
  `approved_by_admin`
) VALUES (
  'admin',
  'admin@billpay.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: 'password'
  'System Administrator',
  'admin',
  'active',
  1,
  1
);

-- Insert sample categories
INSERT INTO `categories` (`name`, `description`) VALUES
('Electronics', 'Electronic devices and accessories'),
('Clothing', 'Apparel and fashion items'),
('Groceries', 'Daily essential food items'),
('Home & Kitchen', 'Home appliances and kitchenware'),
('Books & Stationery', 'Books and office supplies');

-- Insert sample products
INSERT INTO `products` (`name`, `description`, `price`, `category_id`, `stock_quantity`) VALUES
('Wireless Mouse', 'Ergonomic wireless mouse with 2.4GHz connectivity', 25.99, 1, 100),
('Laptop Stand', 'Adjustable aluminum laptop cooling stand', 39.99, 1, 50),
('Premium T-Shirt', '100% Cotton premium t-shirt', 19.99, 2, 200),
('Slim Fit Jeans', 'Modern slim fit denim jeans', 49.99, 2, 150),
('Basmati Rice 5kg', 'Premium quality basmati rice', 15.99, 3, 300),
('Non-Stick Pan', 'High quality non-stick cooking pan', 29.99, 4, 80),
('Notebook Set', 'Set of 5 premium notebooks', 12.99, 5, 200);

-- Insert welcome notification for admin
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`) VALUES
(1, 'Welcome to BillPay Pro!', 'Your admin account has been successfully created. You can now manage the system.', 'success');

-- Insert system notification for admin
INSERT INTO `admin_notifications` (`title`, `message`, `type`) VALUES
('System Ready', 'Online Billing System has been successfully installed and is ready for use.', 'system');

SELECT '✅ Database setup completed successfully!' AS message;
SELECT '📋 Admin Login: admin@billpay.com / password' AS credentials;
SELECT '📋 Note: Customers register with OTP, need admin approval once' AS note;