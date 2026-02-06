-- Gourmet Reserve Restaurant Reservation System
-- Database Schema for MySQL Workbench
-- Run this file to create all tables and sample data

-- Create the database (if it doesn't exist)
CREATE DATABASE IF NOT EXISTS restaurant_reservations;
USE restaurant_reservations;

-- ============================================
-- USERS TABLE
-- Stores customer account information
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- RESERVATIONS TABLE
-- Stores table reservation information
-- ============================================
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    guests INT NOT NULL,
    table_type VARCHAR(50) NOT NULL,
    special_requests TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- MENU_ITEMS TABLE
-- Stores restaurant menu items
-- ============================================
CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(50) NOT NULL,
    image_url VARCHAR(255),
    is_available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- PREORDER_ITEMS TABLE
-- Stores food pre-orders linked to reservations
-- ============================================
CREATE TABLE IF NOT EXISTS preorder_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    special_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
);

-- ============================================
-- SAMPLE DATA: MENU ITEMS
-- ============================================

-- Appetizers
INSERT INTO menu_items (name, description, price, category) VALUES
('Truffle Arancini', 'Crispy risotto balls infused with black truffle, served with garlic aioli', 14.00, 'Appetizers'),
('Burrata Salad', 'Fresh burrata with heirloom tomatoes, basil, and balsamic glaze', 16.00, 'Appetizers'),
('Crispy Calamari', 'Tender calamari rings, lightly fried with lemon herb seasoning', 15.00, 'Appetizers');

-- Main Courses
INSERT INTO menu_items (name, description, price, category) VALUES
('Wagyu Beef Burger', 'Premium wagyu patty with caramelized onions, aged cheddar, brioche bun', 28.00, 'Main Courses'),
('Pan-Seared Salmon', 'Atlantic salmon with lemon butter sauce, seasonal vegetables', 32.00, 'Main Courses'),
('Truffle Pasta', 'House-made fettuccine with black truffle cream and parmesan', 26.00, 'Main Courses'),
('Ribeye Steak', '12oz prime ribeye with herb butter, roasted garlic mashed potatoes', 45.00, 'Main Courses'),
('Chicken Marsala', 'Free-range chicken with wild mushrooms and marsala wine sauce', 24.00, 'Main Courses');

-- Desserts
INSERT INTO menu_items (name, description, price, category) VALUES
('Tiramisu', 'Classic Italian dessert with espresso-soaked ladyfingers and mascarpone', 12.00, 'Desserts'),
('Chocolate Lava Cake', 'Warm chocolate cake with molten center, vanilla ice cream', 14.00, 'Desserts'),
('Crème Brûlée', 'Vanilla bean custard with caramelized sugar crust', 11.00, 'Desserts');

-- Beverages
INSERT INTO menu_items (name, description, price, category) VALUES
('House Wine (Glass)', 'Selection of red or white wine', 12.00, 'Beverages'),
('Craft Cocktail', 'Signature cocktails made with premium spirits', 15.00, 'Beverages'),
('Sparkling Water', 'Premium Italian sparkling water', 6.00, 'Beverages');

-- ============================================
-- TABLE RELATIONSHIP SUMMARY
-- ============================================
-- users (1) ----> (*) reservations (user_id FK)
-- reservations (1) ----> (*) preorder_items (reservation_id FK)
-- menu_items (1) ----> (*) preorder_items (menu_item_id FK)
--
-- Tables: users, reservations, menu_items, preorder_items
-- Total Tables: 4
-- ============================================
