-- Base de données Aadelice E-commerce
-- Création des tables MySQL

-- Table des administrateurs
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des produits
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `stock` INT(11) DEFAULT 0,
  `weight` VARCHAR(50) DEFAULT NULL,
  `image_url` TEXT DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `rating` DECIMAL(3,2) DEFAULT 0.00,
  `reviews` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`),
  KEY `idx_stock` (`stock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des clients
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT 'Dakar',
  `quartier` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des commandes
CREATE TABLE IF NOT EXISTS `orders` (
  `id` VARCHAR(50) NOT NULL,
  `customer_id` INT(11) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `shipping_amount` DECIMAL(10,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `final_amount` DECIMAL(10,2) NOT NULL,
  `shipping_zone` VARCHAR(100) DEFAULT NULL,
  `delivery_address` TEXT NOT NULL,
  `delivery_instructions` TEXT DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'cash',
  `status` ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des articles de commande
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` VARCHAR(50) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_product` (`product_id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des statistiques du site
CREATE TABLE IF NOT EXISTS `site_stats` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `date` DATE NOT NULL UNIQUE,
  `visits` INT(11) DEFAULT 0,
  `orders` INT(11) DEFAULT 0,
  `revenue` DECIMAL(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insérer un administrateur par défaut (username: admin, password: admin123)
-- Le mot de passe est hashé avec password_hash() - à changer en production
INSERT INTO `admins` (`username`, `password`, `email`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@aadelice.sn')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Insérer quelques produits d'exemple
INSERT INTO `products` (`name`, `category`, `price`, `stock`, `weight`, `image_url`, `description`, `rating`, `reviews`) VALUES
('Hitschies Acidulés Halal', 'Sucres', 1500.00, 50, '100g', 'https://images.unsplash.com/photo-1582058091505-f87a2e55a40f?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'Bonbons acidulés halal aux saveurs fruitées', 4.5, 48),
('Soda Bonbons Acidulés', 'Boisson', 1200.00, 30, '33cl', 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'Boisson gazeuse aux saveurs de bonbons acidulés', 4.0, 28),
('Dragées Cadeau Spécial', 'Divers', 3500.00, 20, '200g', 'https://images.unsplash.com/photo-1532117182044-031e7cd916ee?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'Assortiment de dragées pour cadeaux spéciaux', 4.5, 35),
('Box Familiale Maxi', 'Box', 12000.00, 15, '1kg', 'https://images.unsplash.com/photo-1563805042-7684c019e1cb?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'Grande box familiale avec assortiment varié', 5.0, 45),
('Chocolat Diététique', 'Sans sucre', 2500.00, 25, '100g', 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'Chocolat sans sucre pour les régimes diététiques', 4.0, 39)
ON DUPLICATE KEY UPDATE `name`=`name`;

