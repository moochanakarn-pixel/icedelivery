-- Ice Delivery System — Fresh Install for MySQL 8.4
-- Run this once on a new database before first use.
-- The PHP app (config.php) creates all other tables automatically on first load.
--
-- Usage:
--   mysql -u root -p ice_delivery < install.sql
--   (or paste into phpMyAdmin)

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET foreign_key_checks = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -------------------------------------------------------
-- Core tables (not auto-created by PHP)
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS `customers` (
    `id`                        INT NOT NULL AUTO_INCREMENT,
    `name`                      VARCHAR(150) NOT NULL DEFAULT '',
    `phone`                     VARCHAR(30) DEFAULT NULL,
    `route`                     INT NOT NULL DEFAULT 0,
    `route_order`               INT NOT NULL DEFAULT 0,
    `preferred_round`           VARCHAR(20) NOT NULL DEFAULT 'r1',
    `ice_types`                 VARCHAR(100) DEFAULT 'big,small,crush,pack',
    `map_url`                   TEXT NULL,
    `note_text`                 VARCHAR(255) DEFAULT NULL,
    `delivery_point_url`        TEXT NULL,
    `delivery_point_updated_at` DATETIME NULL,
    `latitude`                  DECIMAL(10,7) NULL,
    `longitude`                 DECIMAL(10,7) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_customers_round_route` (`preferred_round`, `route`, `route_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
    `id`            INT NOT NULL AUTO_INCREMENT,
    `customer_id`   INT NOT NULL,
    `order_date`    DATE NOT NULL,
    `order_period`  VARCHAR(20) NOT NULL DEFAULT 'r1',
    `status`        VARCHAR(20) NOT NULL DEFAULT 'pending',
    `delivered`     TINYINT NOT NULL DEFAULT 0,
    `paid`          TINYINT NOT NULL DEFAULT 0,
    `delivery_note` VARCHAR(255) DEFAULT NULL,
    `delivered_qty` INT NULL,
    `delivery_photo` VARCHAR(255) NULL,
    `delivery_lat`  DECIMAL(10,7) NULL,
    `delivery_lng`  DECIMAL(10,7) NULL,
    `delivered_at`  DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_orders_date_period`   (`order_date`, `order_period`),
    KEY `idx_orders_date_customer` (`order_date`, `customer_id`),
    KEY `idx_orders_status`        (`status`, `order_date`),
    KEY `idx_orders_paid_date`     (`paid`, `order_date`),
    KEY `idx_orders_delivered_at`  (`delivered_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
    `id`       INT NOT NULL AUTO_INCREMENT,
    `order_id` INT NOT NULL,
    `ice_type` VARCHAR(20) NOT NULL,
    `qty`      DECIMAL(8,1) NOT NULL DEFAULT 0,
    `price`    INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- After running this file, open the app in a browser once.
-- config.php will auto-create all remaining tables (admin_users, line_users, etc.)
-- and seed the default admin account (username: admin, password: Lucky1234).
-- Change the password immediately after first login.
