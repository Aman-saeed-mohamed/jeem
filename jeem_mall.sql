-- ============================================================
-- JEEM MALL — Complete Database Schema  (v2 — Phases 1-4)
-- ============================================================
-- HOW TO USE:
--   1. Open phpMyAdmin → http://localhost/phpmyadmin
--   2. Click "SQL" tab at the top
--   3. Paste this ENTIRE file and click "Go"
--   4. Done. The database is ready.
--
-- This script DROPS and RECREATES jeem_mall cleanly.
-- Run it once. Re-running it resets all data.
-- ============================================================

DROP DATABASE IF EXISTS jeem_mall;
CREATE DATABASE jeem_mall
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE jeem_mall;

-- ============================================================
-- TABLE: users
-- Stores Customers, Managers, and Admins.
-- password_hash uses bcrypt via password_hash().
-- ============================================================
CREATE TABLE users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name          VARCHAR(150)    NOT NULL,
    email         VARCHAR(191)    NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    role          ENUM('customer','manager','admin') NOT NULL DEFAULT 'customer',
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: user_addresses
-- A customer can have multiple delivery addresses.
-- is_default = 1 flags the address used at checkout.
-- ============================================================
CREATE TABLE user_addresses (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    address    TEXT         NOT NULL,
    is_default TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_ua_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: shops
-- manager_id is nullable so shops can exist without a manager
-- ("available" shops, assignable later by Admin).
-- UNIQUE on manager_id enforces one shop per manager.
-- ============================================================
CREATE TABLE shops (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    manager_id INT UNSIGNED  DEFAULT NULL,
    name       VARCHAR(150)  NOT NULL,
    type       ENUM(
                   'coffeeshop',
                   'restaurant',
                   'clothing_men',
                   'clothing_women',
                   'clothing_kids',
                   'clothing_sports',
                   'electronics_phones',
                   'electronics_laptops',
                   'electronics_accessories'
               ) NOT NULL,
    location   VARCHAR(255)  NOT NULL,
    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_shops_manager (manager_id),
    CONSTRAINT fk_shop_manager
        FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: products
-- Each product belongs to exactly one shop.
-- Deleting a shop cascades to its products.
-- ============================================================
CREATE TABLE products (
    id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    shop_id     INT UNSIGNED   DEFAULT NULL,
    name        VARCHAR(200)   NOT NULL,
    description TEXT,
    price       DECIMAL(10,2)  NOT NULL,
    quantity    INT            NOT NULL DEFAULT 0,
    created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_product_shop
        FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: pictures
-- Multiple images per product. sort_order = 0 is the main image.
-- Deleting a product cascades and removes its picture rows
-- (physical files are deleted via unlink() in PHP first).
-- ============================================================
CREATE TABLE pictures (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED  DEFAULT NULL,
    filename   VARCHAR(255)  NOT NULL,
    sort_order INT           NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    CONSTRAINT fk_picture_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: cart
-- DB-backed cart (not session). UNIQUE(user_id, product_id)
-- allows ON DUPLICATE KEY UPDATE for quantity incrementing.
-- ============================================================
CREATE TABLE cart (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity   INT          NOT NULL DEFAULT 1,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_item (user_id, product_id),
    CONSTRAINT fk_cart_user
        FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_cart_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: orders
-- One order per shop per checkout (multi-vendor split).
-- shop_id uses SET NULL so financial records survive shop deletion.
-- subtotal + tax columns allow re-calculating without order_line.
-- 'Canceled' status used by both manager reject and customer cancel.
-- ============================================================
CREATE TABLE orders (
    id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED   DEFAULT NULL,
    shop_id     INT UNSIGNED   DEFAULT NULL,
    subtotal    DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    tax         DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    total       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    status      ENUM(
                    'Pending',
                    'Accepted',
                    'Being Prepared',
                    'Shipped',
                    'Delivered',
                    'Canceled'
                ) NOT NULL DEFAULT 'Pending',
    created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_order_customer
        FOREIGN KEY (customer_id) REFERENCES users(id)  ON DELETE SET NULL,
    CONSTRAINT fk_order_shop
        FOREIGN KEY (shop_id)     REFERENCES shops(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: order_line
-- Snapshots product_name and unit_price at order time so the
-- order record stays accurate even if the product is later
-- edited or deleted.
-- ============================================================
CREATE TABLE order_line (
    id           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    order_id     INT UNSIGNED   NOT NULL,
    product_id   INT UNSIGNED   DEFAULT NULL,
    product_name VARCHAR(200)   NOT NULL,
    unit_price   DECIMAL(10,2)  NOT NULL,
    quantity     INT            NOT NULL DEFAULT 1,

    PRIMARY KEY (id),
    CONSTRAINT fk_ol_order
        FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    CONSTRAINT fk_ol_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- DEFAULT ADMIN ACCOUNT
-- Email:    admin@jeemmall.com
-- Password: password
-- Change this password immediately after first login!
-- Hash generated with: password_hash('password', PASSWORD_BCRYPT)
-- ============================================================
INSERT INTO users (name, email, password_hash, role)
VALUES (
    'System Administrator',
    'admin@jeemmall.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin'
);
