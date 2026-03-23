-- ============================================================
-- PHASE 1: Supplier Feature — Run this in phpMyAdmin / MySQL
-- ============================================================

-- 1. Add 'supplier' to the users role enum
ALTER TABLE `users`
  MODIFY `role` enum('admin','manager','cashier','supplier') NOT NULL DEFAULT 'cashier';

-- 2. Supplier's own product catalog
CREATE TABLE IF NOT EXISTS `supplier_products` (
  `id`                 int(15)        NOT NULL AUTO_INCREMENT,
  `supplier_id`        int(15)        NOT NULL,
  `sku`                varchar(50)    NOT NULL,
  `name`               varchar(255)   NOT NULL,
  `description`        text           DEFAULT NULL,
  `category`           varchar(100)   DEFAULT NULL,
  `quantity_available` int(11)        NOT NULL DEFAULT 0,
  `unit_price`         decimal(10,2)  NOT NULL,
  `image_path`         varchar(500)   DEFAULT NULL,
  `created_at`         timestamp      NOT NULL DEFAULT current_timestamp(),
  `updated_at`         timestamp      NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Purchase orders — admin selects items from a supplier's catalog
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id`           int(15)                              NOT NULL AUTO_INCREMENT,
  `admin_id`     int(15)                              NOT NULL,
  `supplier_id`  int(15)                              NOT NULL,
  `status`       enum('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `total_amount` decimal(10,2)                        NOT NULL DEFAULT 0.00,
  `notes`        text                                 DEFAULT NULL,
  `created_at`   timestamp                            NOT NULL DEFAULT current_timestamp(),
  `updated_at`   timestamp                            NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id`    (`admin_id`),
  KEY `supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Line items for each purchase order
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id`                  int(15)        NOT NULL AUTO_INCREMENT,
  `purchase_order_id`   int(15)        NOT NULL,
  `supplier_product_id` int(15)        NOT NULL,
  `product_name`        varchar(255)   NOT NULL,
  `product_sku`         varchar(50)    NOT NULL,
  `quantity`            int(11)        NOT NULL,
  `unit_price`          decimal(10,2)  NOT NULL,
  `total_price`         decimal(10,2)  NOT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_id`   (`purchase_order_id`),
  KEY `supplier_product_id` (`supplier_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Foreign key constraints
ALTER TABLE `supplier_products`
  ADD CONSTRAINT `sp_supplier_fk`
    FOREIGN KEY (`supplier_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `po_admin_fk`
    FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `po_supplier_fk`
    FOREIGN KEY (`supplier_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `poi_order_fk`
    FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `poi_product_fk`
    FOREIGN KEY (`supplier_product_id`) REFERENCES `supplier_products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;
