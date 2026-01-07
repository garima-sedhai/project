-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `online_billing_system` 
DEFAULT CHARACTER SET utf8mb4 
DEFAULT COLLATE utf8mb4_unicode_ci;

USE `online_billing_system`;

-- Table: users (for admin and possibly other roles)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100),
    `phone` VARCHAR(20),
    `is_admin` TINYINT(1) DEFAULT 0,
    `is_verified` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_is_active` (`is_active`)
);

-- Table: customers
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `customer_code` VARCHAR(20) UNIQUE NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20),
    `address` TEXT,
    `city` VARCHAR(50),
    `state` VARCHAR(50),
    `zip_code` VARCHAR(20),
    `company_name` VARCHAR(100),
    `tax_id` VARCHAR(50),
    `status` ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    `is_verified` TINYINT(1) DEFAULT 0,
    `verification_token` VARCHAR(100),
    `reset_token` VARCHAR(100),
    `reset_token_expiry` DATETIME,
    `last_login` DATETIME,
    `created_by` INT,
    `approved_by` INT,
    `approved_at` DATETIME,
    `rejected_reason` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_customer_code` (`customer_code`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_approved_by` (`approved_by`)
);

-- Table: verification_otps
CREATE TABLE IF NOT EXISTS `verification_otps` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `email` VARCHAR(100) NOT NULL,
    `otp` VARCHAR(10) NOT NULL,
    `type` ENUM('registration', 'password_reset', 'email_change') DEFAULT 'registration',
    `is_used` TINYINT(1) DEFAULT 0,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email_type` (`email`, `type`),
    INDEX `idx_otp` (`otp`),
    INDEX `idx_expires_at` (`expires_at`)
);

-- Table: categories
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`)
);

-- Table: products
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `category_id` INT,
    `price` DECIMAL(10,2) NOT NULL,
    `unit` VARCHAR(20),
    `stock_quantity` INT DEFAULT 0,
    `min_stock` INT DEFAULT 10,
    `image` VARCHAR(255),
    `taxable` TINYINT(1) DEFAULT 1,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_category` (`category_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_name` (`name`)
);

-- Table: bills
CREATE TABLE IF NOT EXISTS `bills` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `bill_number` VARCHAR(50) UNIQUE NOT NULL,
    `customer_id` INT NOT NULL,
    `bill_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `tax_amount` DECIMAL(10,2) NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `discount` DECIMAL(10,2) DEFAULT 0,
    `notes` TEXT,
    `status` ENUM('draft', 'pending', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    `payment_method` VARCHAR(50),
    `payment_date` DATETIME,
    `transaction_id` VARCHAR(100),
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
    INDEX `idx_bill_number` (`bill_number`),
    INDEX `idx_customer` (`customer_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_bill_date` (`bill_date`)
);

-- Table: bill_items
CREATE TABLE IF NOT EXISTS `bill_items` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `bill_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total` DECIMAL(10,2) NOT NULL,
    `tax_rate` DECIMAL(5,2) DEFAULT 13,
    `tax_amount` DECIMAL(10,2),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    INDEX `idx_bill` (`bill_id`),
    INDEX `idx_product` (`product_id`)
);

-- Table: payments
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `bill_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `transaction_id` VARCHAR(100),
    `reference_number` VARCHAR(100),
    `notes` TEXT,
    `status` ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
    INDEX `idx_bill` (`bill_id`),
    INDEX `idx_customer` (`customer_id`),
    INDEX `idx_payment_date` (`payment_date`)
);

-- Table: audit_logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT,
    `user_type` ENUM('admin', 'customer') NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(50),
    `record_id` INT,
    `old_values` JSON,
    `new_values` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`, `user_type`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
);

-- Table: settings
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    `value` TEXT,
    `type` ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    `category` VARCHAR(50),
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_key` (`key`),
    INDEX `idx_category` (`category`)
);

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `is_admin`, `is_verified`, `is_active`) 
VALUES 
('admin', 'admin@billpaypro.com', '$2y$10$YourHashedPasswordHere', 'Administrator', 1, 1, 1)
ON DUPLECTE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;

-- Insert default settings
INSERT INTO `settings` (`key`, `value`, `type`, `category`, `description`) VALUES
('site_name', 'BillPay Pro - Online Billing System', 'string', 'general', 'Website name'),
('site_url', 'http://localhost/project/', 'string', 'general', 'Website URL'),
('currency', 'NPR', 'string', 'billing', 'Default currency'),
('currency_symbol', '₨', 'string', 'billing', 'Currency symbol'),
('tax_rate', '13', 'number', 'billing', 'Default tax rate percentage'),
('bill_prefix', 'BILL', 'string', 'billing', 'Prefix for bill numbers'),
('invoice_prefix', 'INV', 'string', 'billing', 'Prefix for invoice numbers'),
('smtp_enabled', 'true', 'boolean', 'email', 'Enable/disable SMTP'),
('smtp_host', 'smtp.gmail.com', 'string', 'email', 'SMTP host'),
('smtp_port', '587', 'number', 'email', 'SMTP port'),
('smtp_username', 'sedhaigarima183@gmail.com', 'string', 'email', 'SMTP username'),
('smtp_from_email', 'sedhaigarima183@gmail.com', 'string', 'email', 'From email address'),
('smtp_from_name', 'BillPay Pro System', 'string', 'email', 'From name')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = CURRENT_TIMESTAMP;

-- Insert default categories
INSERT INTO `categories` (`name`, `description`, `status`) VALUES
('Electronics', 'Electronic items and devices', 'active'),
('Furniture', 'Office and home furniture', 'active'),
('Stationery', 'Office stationery items', 'active'),
('Software', 'Software licenses and subscriptions', 'active'),
('Services', 'Consulting and other services', 'active')
ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;

-- Insert sample products
INSERT INTO `products` (`name`, `description`, `category_id`, `price`, `unit`, `stock_quantity`, `taxable`, `status`) VALUES
('Laptop', 'High performance laptop', 1, 80000.00, 'piece', 10, 1, 'active'),
('Office Chair', 'Ergonomic office chair', 2, 15000.00, 'piece', 15, 1, 'active'),
('Printer', 'Color laser printer', 1, 25000.00, 'piece', 5, 1, 'active'),
('Notebook', 'Premium quality notebook', 3, 500.00, 'pack', 100, 1, 'active'),
('Microsoft Office', 'Office 365 subscription', 4, 12000.00, 'year', 50, 1, 'active')
ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;