
CREATE DATABASE IF NOT EXISTS `karinderya_mo` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `karinderya_mo`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20),
  `delivery_address` TEXT,
  `role` ENUM('owner','admin','rider','customer') NOT NULL DEFAULT 'customer',
  `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `email_idx` (`email`),
  INDEX `role_idx` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `image` VARCHAR(255),
  `description` TEXT,
  `availability` ENUM('available','unavailable') NOT NULL DEFAULT 'available',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `category_idx` (`category`),
  INDEX `availability_idx` (`availability`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT UNSIGNED NOT NULL,
  `total_amount` DECIMAL(10, 2) NOT NULL,
  `shipping_fee` DECIMAL(10, 2) NOT NULL DEFAULT 58.00,
  `status` ENUM('pending_payment','pending','approved','rejected','preparing','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `delivery_address` TEXT,
  `payment_method` ENUM('cod','wallet','online') NOT NULL DEFAULT 'cod',
  `payment_ref_no` VARCHAR(255) NULL,
  `rider_id` INT UNSIGNED,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`rider_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `customer_idx` (`customer_id`),
  INDEX `status_idx` (`status`),
  INDEX `rider_idx` (`rider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS shipping_fee DECIMAL(10, 2) NOT NULL DEFAULT 58.00 
AFTER total_amount;
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `menu_item_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `price` DECIMAL(10, 2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items`(`id`) ON DELETE CASCADE,
  INDEX `order_idx` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `deliveries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `rider_id` INT UNSIGNED,
  `status` ENUM('pending','picked_up','in_transit','delivered','accepted','declined') NOT NULL DEFAULT 'pending',
  `amount` DECIMAL(10, 2),
  `base_pay` DECIMAL(10, 2),
  `delivered_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`rider_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `order_id_unique` (`order_id`),
  INDEX `rider_idx` (`rider_id`),
  INDEX `status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE deliveries 
MODIFY COLUMN rider_id INT UNSIGNED NULL;

ALTER TABLE deliveries 
MODIFY COLUMN status ENUM('pending','picked_up','in_transit','delivered','accepted','declined');

ALTER TABLE deliveries 
DROP FOREIGN KEY deliveries_ibfk_2;

ALTER TABLE deliveries 
ADD CONSTRAINT deliveries_ibfk_2 FOREIGN KEY (rider_id) REFERENCES users(id) ON DELETE SET NULL;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_name` VARCHAR(100) NOT NULL UNIQUE,
  `value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `menu_items` (`name`, `category`, `price`, `image`, `description`, `availability`) VALUES
('Pork Adobo', 'Pork', 149.00, 'images/Pork-Adobo.jpg', 'Tender pork stewed in vinegar and soy sauce', 'available'),
('Pork Menudo', 'Pork', 139.00, 'images/Pork-Menudo.jpg', 'Savory pork and liver stew with vegetables', 'available'),
('Pork Giniling', 'Pork', 119.00, 'images/Pork-Giniling.jpg', 'Ground pork with potatoes and peas', 'available'),
('Pork Sinigang', 'Pork', 129.00, 'images/Pork-Sinigang.jpg', 'Sour pork soup with tamarind and vegetables', 'available'),
('Chicken Adobo', 'Chicken', 139.00, 'images/Chicken-Adobo.jpg', 'Tender chicken in rich brown sauce', 'available'),
('Fried Chicken', 'Chicken', 159.00, 'images/Fried-Chicken.jpg', 'Crispy fried chicken, golden brown', 'available'),
('Tinolang Manok', 'Chicken', 119.00, 'images/Tinolang-Manok.jpg', 'Chicken soup with ginger and papaya', 'available'),
('Chicken Afritada', 'Chicken', 129.00, 'images/Chicken-Afritada.jpg', 'Chicken in tomato-based stew', 'available'),
('Chicken Curry', 'Chicken', 149.00, 'images/Chicken-Curry.jpg', 'Creamy coconut milk chicken curry', 'available'),
('Pinakbet', 'Gulay / Vegetable', 99.00, 'images/Pinakbet.jpg', 'Mixed vegetables in shrimp paste', 'available'),
('Ginisang Ampalaya', 'Gulay / Vegetable', 89.00, 'images/Ginisang-Amplaya.jpg', 'Sautéed bitter melon with egg', 'available'),
('Monggo', 'Gulay / Vegetable', 99.00, 'images/Monggo-Gisado.jpg', 'Mung bean soup with pork', 'available'),
('Chopsuey', 'Gulay / Vegetable', 109.00, 'images/Chopsuey.jpg', 'Stir-fried mixed vegetables', 'available');
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `delivery_address`, `role`, `status`) VALUES
('Admin User', 'admin@karinderya.com', 'admin123', '09123456789', 'Admin Office, Manila', 'admin', 'active'),
('John Rider', 'rider@karinderya.com', 'rider123', '09987654321', 'Rider Base Station, Manila', 'rider', 'active'),
('Maria Santos', 'customer@karinderya.com', 'customer123', '09112345678', '123 Main Street, Brgy. San Isidro, Manila', 'customer', 'active'),
('Juan Dela Cruz', 'juan@karinderya.com', 'password123', '09555666777', '456 Oak Avenue, Brgy. Tatalon, Quezon City', 'customer', 'active');
INSERT INTO `settings` (`key_name`, `value`) VALUES
('site_name', 'Karinderya Mo'),
('site_description', 'Philippine Food Delivery System'),
('contact_email', 'support@karinderya.com'),
('contact_phone', '09123456789'),
('theme_primary_color', '#FF8B54'),
('theme_secondary_color', '#FF6B54');
CREATE TABLE IF NOT EXISTS `wallet` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `balance` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_wallet` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `transaction_type` ENUM('deposit','payment','refund') NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `balance_before` DECIMAL(10, 2) NOT NULL,
  `balance_after` DECIMAL(10, 2) NOT NULL,
  `reference_type` VARCHAR(50) NULL COMMENT 'e.g., order, manual',
  `reference_id` INT UNSIGNED NULL COMMENT 'e.g., order_id',
  `description` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `user_idx` (`user_id`),
  INDEX `type_idx` (`transaction_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `age` INT UNSIGNED NOT NULL,
  `gender` VARCHAR(20) NOT NULL,
  `contact` VARCHAR(50) NOT NULL,
  `address` TEXT NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `civil_status` VARCHAR(50) NOT NULL,
  `nationality` VARCHAR(80) NOT NULL,
  `pin_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_bank_account` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `wallet` (`user_id`, `balance`) 
SELECT `id`, 0.00 FROM `users` WHERE `role` = 'customer'
ON DUPLICATE KEY UPDATE `balance` = `balance`;
