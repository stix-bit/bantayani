-- BantayAni Complete Database Setup
-- This file combines all schema, tables, triggers, and sample data into one clean script.
-- Generated on: 2026-03-17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bantayani_db`
--
CREATE DATABASE IF NOT EXISTS `bantayani_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `bantayani_db`;

-- --------------------------------------------------------
-- Table structure for table `announcements`
-- --------------------------------------------------------

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `announcement_type` enum('General','Weather','System','Event','Alert') DEFAULT 'General',
  `priority` enum('Low','Medium','High','Urgent') DEFAULT 'Medium',
  `target_audience` enum('All','Farmers','Buyers') DEFAULT 'All',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `image_path` varchar(255) DEFAULT NULL,
  `views_count` int(11) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`announcement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `announcements` (`announcement_id`, `title`, `content`, `announcement_type`, `priority`, `target_audience`, `created_by`, `created_at`, `expires_at`, `is_active`, `image_path`, `views_count`) VALUES
(1, 'Test', 'Testesttestestest', 'General', 'Medium', 'All', 2, '2026-02-24 12:47:01', NULL, 1, 'uploads/announcements/announcement_699d9dc5c447c.jpg', 3),
(2, 'Test Announcement', 'Test Announcement', 'General', 'Low', 'All', 2, '2026-02-24 14:45:45', '2026-02-25 22:45:00', 1, 'uploads/announcements/announcement_699db99944263.png', 3);

-- --------------------------------------------------------
-- Table structure for table `announcement_views`
-- --------------------------------------------------------

CREATE TABLE `announcement_views` (
  `view_id` int(11) NOT NULL AUTO_INCREMENT,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`view_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `role` enum('Farmer','Buyer','Admin') NOT NULL,
  `first_name` varchar(45) NOT NULL,
  `middle_name` varchar(45) NOT NULL,
  `last_name` varchar(45) NOT NULL,
  `email` varchar(45) NOT NULL,
  `password` varchar(255) NOT NULL,
  `contact_number` varchar(45) DEFAULT NULL,
  `address` varchar(45) NOT NULL,
  `is_verified` tinyint(4) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `img_path` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email_UNIQUE` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `users` (`user_id`, `role`, `first_name`, `middle_name`, `last_name`, `email`, `password`, `contact_number`, `address`, `is_verified`, `created_at`, `img_path`) VALUES
(2, 'Admin', 'admin', 'admin', 'admin', 'admin@gmail.com', '$2y$10$brUoPXA42uj.eCaxH5xmmOwipROMiwa0UpsR5vXXTTDUed2K73hkO', '213', 'dasfadsf', 0, '2026-02-24 02:23:19', 'images/uploads/profiles/profile_699d0b979c4806.64321523.png'),
(4, 'Farmer', 'The', 'Test', 'Account', 'test4@gmail.com', '$2y$10$9yyzDPdLEy5nWM/2ekxSse7IH6mWA5ban/ihAJ74pQ7Wgn6ls2tIa', '09141502722', 'test address', 0, '2026-03-12 09:21:22', 'images/uploads/profiles/profile_69b28591de65a9.16368399.png'),
(5, 'Buyer', 'buyer', 'test', 'test', 'buyer@gmail.com', '$2y$10$BWubMIaUZrt0bpFmNuoT7eAyRCGtbKrDjOcJOqH7L05JG9nDA8b5G', '123123123123', 'test', 0, '2026-03-12 09:36:24', 'images/uploads/profiles/profile_69b28918349530.56552294.png'),
(6, 'Farmer', 'farmer', 'test', 'test', 'farmer@gmail.com', '$2y$10$m37WSvDYoE14lyiDq3WqLeAoXrhkx9QFYINXGZUPnx3eXo8yEj7ga', '0123123123', 'test', 0, '2026-03-12 09:37:56', 'images/uploads/profiles/profile_69b28973f0f5b5.17134184.png'),
(7, 'Farmer', 'farmer2', 'test', 'test', 'farmer2@gmail.com', '$2y$10$mlyVAoYB.VA32bx0j8XRBee3niaxQPNQu64SxqIIqFYAAYFHkLNDK', '0123123123', 'test', 1, '2026-03-12 09:46:45', 'images/uploads/profiles/profile_69b28b8571c439.25301610.png');

-- --------------------------------------------------------
-- Table structure for table `buyer_profiles`
-- --------------------------------------------------------

CREATE TABLE `buyer_profiles` (
  `buyer_id` int(11) NOT NULL,
  `preferred_payment_method` enum('Cash','Online') DEFAULT 'Cash',
  `verified` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`buyer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `buyer_profiles` (`buyer_id`, `preferred_payment_method`, `verified`) VALUES
(5, 'Cash', 0);

-- --------------------------------------------------------
-- Table structure for table `farmer_profiles`
-- --------------------------------------------------------

CREATE TABLE `farmer_profiles` (
  `farmer_id` int(11) NOT NULL,
  `farm_name` varchar(45) NOT NULL,
  `farm_location` varchar(255) NOT NULL,
  `farm_img_path` varchar(255) DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`farmer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `farmer_profiles` (`farmer_id`, `farm_name`, `farm_location`, `farm_img_path`, `verified_by`, `verified_at`, `region`) VALUES
(2, 'asdf', 'asdf', NULL, NULL, NULL, NULL),
(4, 'The Account', 'test address', NULL, NULL, NULL, 'Manila'),
(6, 'farmer', 'test', 'images/uploads/farms/farm_69b28973f189e8.05241689.png', NULL, NULL, 'Quezon'),
(7, 'farmer2', 'test', 'images/uploads/farms/farm_69b28b85723c36.50734635.png', 2, '2026-03-12 12:14:25', 'Nueva Ecija');

-- --------------------------------------------------------
-- Table structure for table `farm_images`
-- --------------------------------------------------------

CREATE TABLE `farm_images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `farmer_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------
-- Table structure for table `crop_categories`
-- --------------------------------------------------------

CREATE TABLE `crop_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `crop_categories` (`category_id`, `category_name`, `display_order`) VALUES
(1, 'Rice Crops', 0),
(2, 'Corn Crops', 0),
(3, 'Coconut Crops', 0),
(4, 'Banana Crops', 0),
(5, 'Root Crops', 0),
(6, 'Fruit Crops', 0),
(7, 'Vegetable Crops', 0),
(8, 'Leafy Vegetables', 0),
(9, 'Spices and Herbs', 0),
(10, 'Industrial Crops', 0);

-- --------------------------------------------------------
-- Table structure for table `crops`
-- --------------------------------------------------------

CREATE TABLE `crops` (
  `crop_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL DEFAULT 8,
  `crop_name` varchar(45) NOT NULL,
  `unit` enum('kg','g','pieces','sack','bundle') NOT NULL DEFAULT 'kg',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`crop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `crops` (`crop_id`, `category_id`, `crop_name`, `unit`) VALUES
(42, 12, 'IR64', 'kg'),
(43, 12, 'NSIC Rc222', 'kg'),
(44, 12, 'NSIC Rc216', 'kg'),
(45, 12, 'Dinorado', 'kg'),
(46, 12, 'Sinandomeng', 'kg'),
(47, 12, 'Milagrosa', 'kg'),
(48, 12, 'Black Rice', 'kg'),
(49, 12, 'Red Rice', 'kg'),
(50, 12, 'Jasmine Rice', 'kg'),
(51, 12, 'Basmati Rice', 'kg'),
(52, 1, 'IR64', 'kg'),
(53, 1, 'NSIC Rc222', 'kg'),
(54, 1, 'NSIC Rc216', 'kg'),
(55, 1, 'Dinorado', 'kg'),
(56, 1, 'Sinandomeng', 'kg'),
(57, 1, 'Milagrosa', 'kg'),
(58, 1, 'Black Rice', 'kg'),
(59, 1, 'Red Rice', 'kg'),
(60, 1, 'Jasmine Rice', 'kg'),
(61, 1, 'Basmati Rice', 'kg'),
(62, 2, 'Yellow Corn', 'kg'),
(63, 2, 'White Corn', 'kg'),
(64, 2, 'Sweet Corn', 'kg'),
(65, 2, 'Glutinous Corn', 'kg'),
(66, 2, 'Baby Corn', 'kg'),
(67, 2, 'Hybrid Corn', 'kg'),
(68, 2, 'Open Pollinated Corn', 'kg'),
(69, 2, 'Pioneer Corn', 'kg'),
(70, 2, 'Bt Corn', 'kg'),
(71, 2, 'Dent Corn', 'kg'),
(72, 3, 'Laguna Tall Coconut', 'kg'),
(73, 3, 'Dwarf Coconut', 'kg'),
(74, 3, 'Malayan Yellow Dwarf', 'kg'),
(75, 3, 'Malayan Red Dwarf', 'kg'),
(76, 3, 'Tacunan Green Dwarf', 'kg'),
(77, 3, 'Aromatic Green Dwarf', 'kg'),
(78, 3, 'West African Tall', 'kg'),
(79, 3, 'Catigan Dwarf', 'kg'),
(80, 3, 'MATAG Hybrid Coconut', 'kg'),
(81, 3, 'King Coconut', 'kg'),
(82, 4, 'Lakatan Banana', 'kg'),
(83, 4, 'Cavendish Banana', 'kg'),
(84, 4, 'Saba Banana', 'kg'),
(85, 4, 'Latundan Banana', 'kg'),
(86, 4, 'Cardava Banana', 'kg'),
(87, 4, 'Bungulan Banana', 'kg'),
(88, 4, 'Senorita Banana', 'kg'),
(89, 4, 'Plantain Banana', 'kg'),
(90, 4, 'Red Banana', 'kg'),
(91, 4, 'Blue Java Banana', 'kg'),
(92, 5, 'Mango', 'kg'),
(93, 5, 'Pineapple', 'kg'),
(94, 5, 'Papaya', 'kg'),
(95, 5, 'Guava', 'kg'),
(96, 5, 'Jackfruit', 'kg'),
(97, 5, 'Avocado', 'kg'),
(98, 5, 'Rambutan', 'kg'),
(99, 5, 'Lanzones', 'kg'),
(100, 5, 'Mangosteen', 'kg'),
(101, 5, 'Dragon Fruit', 'kg'),
(102, 6, 'Tomato', 'kg'),
(103, 6, 'Eggplant', 'kg'),
(104, 6, 'Okra', 'kg'),
(105, 6, 'Bitter Gourd', 'kg'),
(106, 6, 'Squash', 'kg'),
(107, 6, 'Cabbage', 'kg'),
(108, 6, 'Carrot', 'kg'),
(109, 6, 'Cauliflower', 'kg'),
(110, 6, 'Bell Pepper', 'kg'),
(111, 6, 'Cucumber', 'kg'),
(112, 7, 'Cassava', 'kg'),
(113, 7, 'Sweet Potato', 'kg'),
(114, 7, 'Taro', 'kg'),
(115, 7, 'Purple Yam', 'kg'),
(116, 7, 'Potato', 'kg'),
(117, 7, 'Radish', 'kg'),
(118, 7, 'Turnip', 'kg'),
(119, 7, 'Beetroot', 'kg'),
(120, 7, 'Jicama', 'kg'),
(121, 7, 'Arrowroot', 'kg'),
(122, 8, 'Ginger', 'kg'),
(123, 8, 'Garlic', 'kg'),
(124, 8, 'Onion', 'kg'),
(125, 8, 'Turmeric', 'kg'),
(126, 8, 'Lemongrass', 'kg'),
(127, 8, 'Basil', 'kg'),
(128, 8, 'Oregano', 'kg'),
(129, 8, 'Mint', 'kg'),
(130, 8, 'Chili Pepper', 'kg'),
(131, 8, 'Bay Leaf', 'kg'),
(132, 9, 'Sugarcane', 'kg'),
(133, 9, 'Coffee', 'kg'),
(134, 9, 'Cacao', 'kg'),
(135, 9, 'Rubber', 'kg'),
(136, 9, 'Tobacco', 'kg'),
(137, 9, 'Oil Palm', 'kg'),
(138, 9, 'Abaca', 'kg'),
(139, 9, 'Cotton', 'kg'),
(140, 9, 'Jute', 'kg'),
(141, 9, 'Indigo', 'kg'),
(142, 10, 'Mung Bean', 'kg'),
(143, 10, 'Peanut', 'kg'),
(144, 10, 'Soybean', 'kg'),
(145, 10, 'String Beans', 'kg'),
(146, 10, 'Kidney Beans', 'kg'),
(147, 10, 'Chickpea', 'kg'),
(148, 10, 'Lentil', 'kg'),
(149, 10, 'Pigeon Pea', 'kg'),
(150, 10, 'Lima Bean', 'kg'),
(151, 10, 'Black Bean', 'kg');

-- --------------------------------------------------------
-- Table structure for table `crops_inventory`
-- --------------------------------------------------------

CREATE TABLE `crops_inventory` (
  `inventory_id` int(11) NOT NULL AUTO_INCREMENT,
  `farmer_id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `unit` enum('kg','g','pieces','sack','bundle') NOT NULL DEFAULT 'kg',
  `harvest_date` date DEFAULT NULL,
  `harvest_status` enum('Scheduled','Confirmed','Cancelled') NOT NULL DEFAULT 'Scheduled',
  `harvest_confirmed_at` datetime DEFAULT NULL,
  `harvest_cancelled_at` datetime DEFAULT NULL,
  `harvest_notification_seen_at` datetime DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`inventory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `crops_inventory` (`inventory_id`, `farmer_id`, `crop_id`, `quantity`, `unit`, `harvest_date`, `harvest_status`, `harvest_confirmed_at`, `harvest_cancelled_at`, `harvest_notification_seen_at`, `price`, `created_at`) VALUES
(14, 7, 52, 30.00, 'kg', '2026-03-12', 'Confirmed', '2026-03-12 17:58:18', NULL, '2026-03-12 17:58:12', 45.00, '2026-03-12 09:58:11'),
(16, 7, 54, 30.00, 'kg', '2026-03-12', 'Confirmed', '2026-03-12 18:02:15', NULL, '2026-03-12 18:02:12', 45.00, '2026-03-12 10:02:11'),
(21, 7, 55, 30.00, 'kg', '2026-03-12', 'Confirmed', '2026-03-12 18:50:10', NULL, '2026-03-12 18:50:09', 55.00, '2026-03-12 10:50:09'),
(22, 7, 53, 30.00, 'kg', '2026-03-12', 'Confirmed', '2026-03-12 18:53:14', NULL, '2026-03-12 18:53:13', 45.00, '2026-03-12 10:53:13'),
(23, 7, 56, 30.00, 'kg', '2026-03-12', 'Confirmed', '2026-03-12 18:54:56', NULL, '2026-03-12 18:54:55', 55.00, '2026-03-12 10:54:54'),
(24, 7, 57, 20.00, 'kg', '2026-03-12', 'Confirmed', '2026-03-12 18:56:03', NULL, '2026-03-12 18:56:00', 60.00, '2026-03-12 10:56:00'),
(25, 7, 58, 30.00, 'kg', '2026-03-12', 'Confirmed', '2026-03-12 18:57:10', NULL, '2026-03-12 18:57:09', 90.00, '2026-03-12 10:57:09'),
(26, 7, 59, 30.00, 'kg', '2026-03-12', 'Confirmed', '2026-03-12 18:58:43', NULL, '2026-03-12 18:58:32', 85.00, '2026-03-12 10:58:32'),
(27, 7, 60, 10.00, '', '2026-03-12', 'Confirmed', '2026-03-12 19:00:09', NULL, '2026-03-12 19:00:08', 75.00, '2026-03-12 11:00:08'),
(28, 7, 61, 25.00, 'kg', '2026-03-12', 'Confirmed', '2026-03-12 19:01:49', NULL, '2026-03-12 19:01:48', 150.00, '2026-03-12 11:01:48'),
(29, 6, 60, 2.00, 'kg', '2026-03-12', 'Confirmed', '2026-03-12 19:30:12', NULL, '2026-03-12 19:30:10', 65.00, '2026-03-12 11:30:10');

-- --------------------------------------------------------
-- Table structure for table `crop_images`
-- --------------------------------------------------------

CREATE TABLE `crop_images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `crop_images` (`image_id`, `inventory_id`, `image_path`, `is_primary`, `uploaded_at`) VALUES
(16, 14, 'images/uploads/crops/crop_69b28e33cc25f.jpg', 1, '2026-03-12 09:58:11'),
(18, 16, 'images/uploads/crops/crop_69b28f23d0163.jpg', 1, '2026-03-12 10:02:11'),
(22, 21, 'images/uploads/crops/crop_69b29a6127711.jpg', 1, '2026-03-12 10:50:09'),
(24, 22, 'images/uploads/crops/crop_69b29b1992d52.jpg', 1, '2026-03-12 10:53:13'),
(25, 23, 'images/uploads/crops/crop_69b29b7ec87da.jpg', 1, '2026-03-12 10:54:54'),
(26, 24, 'images/uploads/crops/crop_69b29bc06515a.jpg', 1, '2026-03-12 10:56:00'),
(28, 24, 'images/uploads/crops/crop_69b29bc06678c.jpg', 0, '2026-03-12 10:56:00'),
(29, 24, 'images/uploads/crops/crop_69b29bc066b30.jpg', 0, '2026-03-12 10:56:00'),
(30, 24, 'images/uploads/crops/crop_69b29bc0683b2.jpg', 0, '2026-03-12 10:56:00'),
(31, 25, 'images/uploads/crops/crop_69b29c052b6d3.webp', 1, '2026-03-12 10:57:09'),
(32, 26, 'images/uploads/crops/crop_69b29c58139f5.jpg', 1, '2026-03-12 10:58:32'),
(33, 27, 'images/uploads/crops/crop_69b29cb8788a0.jpg', 1, '2026-03-12 11:00:08'),
(34, 28, 'images/uploads/crops/crop_69b29d1c8848c.jpg', 1, '2026-03-12 11:01:48'),
(35, 29, 'images/uploads/crops/crop_69b2a3c216293.jpg', 1, '2026-03-12 11:30:10');

-- --------------------------------------------------------
-- Table structure for table `farmer_verification`
-- --------------------------------------------------------

CREATE TABLE `farmer_verification` (
  `verification_id` int(11) NOT NULL AUTO_INCREMENT,
  `farmer_id` int(11) NOT NULL,
  `certificate_type` enum('Business Permit','Agricultural License','Tax Identification','Others') NOT NULL,
  `certificate_name` varchar(255) NOT NULL,
  `certificate_path` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `submitted_at` timestamp NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  PRIMARY KEY (`verification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `farmer_verification` (`verification_id`, `farmer_id`, `certificate_type`, `certificate_name`, `certificate_path`, `status`, `submitted_at`, `reviewed_at`, `reviewed_by`, `admin_notes`) VALUES
(3, 7, 'Business Permit', 'test', 'uploads/certificates/certificate_7_69b2ae157af9b.jpg', 'Approved', '2026-03-12 12:14:13', '2026-03-12 12:14:25', 2, '');

-- --------------------------------------------------------
-- Table structure for table `orders`
-- --------------------------------------------------------

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `buyer_id` int(11) NOT NULL,
  `order_date` timestamp NULL DEFAULT current_timestamp(),
  `order_status` enum('Pending','Cancelled','Confirmed','Delivered','Shipped') DEFAULT 'Pending',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `orders` (`order_id`, `buyer_id`, `order_date`, `order_status`) VALUES
(15, 5, '2026-03-12 11:19:52', 'Confirmed'),
(16, 5, '2026-03-12 11:20:26', 'Confirmed'),
(17, 5, '2026-03-12 11:30:48', 'Confirmed'),
(18, 5, '2026-03-12 13:01:51', 'Confirmed'),
(19, 5, '2026-03-12 13:18:32', 'Confirmed'),
(20, 5, '2026-03-12 13:18:43', 'Confirmed');

-- --------------------------------------------------------
-- Table structure for table `invoices`
-- --------------------------------------------------------

CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `order_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('Unpaid','Partially Paid','Paid','Overdue') DEFAULT 'Unpaid',
  `payment_method` enum('Cash','Bank Transfer','GCash','PayMaya','Credit Card','Check') DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `invoice_number` (`invoice_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `invoices` (`invoice_id`, `invoice_number`, `order_id`, `buyer_id`, `farmer_id`, `subtotal`, `tax_amount`, `discount_amount`, `total_amount`, `payment_status`, `payment_method`, `payment_date`, `due_date`, `notes`, `created_at`, `updated_at`) VALUES
(6, 'INV-202603-000016', 16, 5, 7, 1050.00, 0.00, 0.00, 1050.00, 'Unpaid', NULL, NULL, '2026-03-19', NULL, '2026-03-12 11:21:05', '2026-03-12 11:21:05'),
(7, 'INV-202603-000015', 15, 5, 7, 300.00, 0.00, 0.00, 300.00, 'Unpaid', NULL, NULL, '2026-03-19', NULL, '2026-03-12 11:21:25', '2026-03-12 11:21:25'),
(13, 'INV-202603-000017', 17, 5, 7, 750.00, 0.00, 0.00, 750.00, 'Paid', NULL, NULL, '2026-03-19', NULL, '2026-03-12 13:14:57', '2026-03-12 13:30:06'),
(14, 'INV-202603-000018', 18, 5, 7, 750.00, 0.00, 0.00, 750.00, 'Paid', NULL, NULL, '2026-03-19', NULL, '2026-03-12 13:15:40', '2026-03-12 13:30:05'),
(15, 'INV-202603-000020', 20, 5, 6, 195.00, 0.00, 0.00, 195.00, 'Unpaid', NULL, NULL, '2026-03-19', NULL, '2026-03-12 13:19:06', '2026-03-12 13:19:06'),
(16, 'INV-202603-000019', 19, 5, 7, 1125.00, 0.00, 0.00, 1125.00, 'Paid', NULL, NULL, '2026-03-19', NULL, '2026-03-12 13:19:31', '2026-03-12 13:30:03');

-- --------------------------------------------------------
-- Table structure for table `invoice_items`
-- --------------------------------------------------------

CREATE TABLE `invoice_items` (
  `invoice_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `crop_name` varchar(100) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`invoice_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `invoice_items` (`invoice_item_id`, `invoice_id`, `inventory_id`, `crop_name`, `quantity`, `unit_price`, `total_price`) VALUES
(6, 6, 24, 'Milagrosa', 5.00, 60.00, 300.00),
(7, 6, 28, 'Basmati Rice', 5.00, 150.00, 750.00),
(9, 7, 24, 'Milagrosa', 5.00, 60.00, 300.00),
(13, 13, NULL, 'Jasmine Rice', 10.00, 75.00, 750.00),
(14, 14, NULL, 'Jasmine Rice', 10.00, 75.00, 750.00),
(15, 15, 29, 'Jasmine Rice', 3.00, 65.00, 195.00),
(16, 16, NULL, 'Jasmine Rice', 15.00, 75.00, 1125.00);

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `notification_type` enum('Weather','Order','System') DEFAULT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------
-- Table structure for table `order_items`
-- --------------------------------------------------------

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `pool_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `order_items` (`order_item_id`, `order_id`, `inventory_id`, `pool_id`, `quantity`) VALUES
(15, 15, 24, NULL, 5.00),
(16, 16, 24, NULL, 5.00),
(17, 16, 28, NULL, 5.00),
(18, 17, NULL, 3, 10.00),
(19, 18, NULL, 3, 10.00),
(20, 19, NULL, 3, 15.00),
(21, 20, 29, NULL, 3.00);

-- --------------------------------------------------------
-- Table structure for table `payment`
-- --------------------------------------------------------

CREATE TABLE `payment` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `payment_method` enum('Cash','Online') DEFAULT NULL,
  `payment_status` enum('Pending','Paid','Cancelled') DEFAULT 'Pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `payment` (`payment_id`, `order_id`, `payment_method`, `payment_status`, `payment_date`) VALUES
(15, 15, 'Cash', 'Paid', '2026-03-12 11:21:25'),
(16, 16, 'Cash', 'Paid', '2026-03-12 11:21:05'),
(17, 17, 'Cash', 'Paid', '2026-03-12 13:30:06'),
(18, 18, 'Cash', 'Paid', '2026-03-12 13:30:05'),
(19, 19, 'Cash', 'Paid', '2026-03-12 13:30:03'),
(20, 20, 'Cash', 'Paid', '2026-03-12 13:19:06');

-- --------------------------------------------------------
-- Table structure for table `ratings`
-- --------------------------------------------------------

CREATE TABLE `ratings` (
  `rating_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rating` tinyint(3) UNSIGNED NOT NULL COMMENT '1–5 stars',
  `comment` text DEFAULT NULL,
  `farmer_id` int(10) UNSIGNED NOT NULL,
  `buyer_id` int(10) UNSIGNED NOT NULL,
  `inventory_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rating_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ratings` (`rating_id`, `rating`, `comment`, `farmer_id`, `buyer_id`, `inventory_id`, `created_at`) VALUES
(1, 4, 'test', 1, 3, 2, '2026-02-24 22:34:31'),
(2, 5, 'test', 1, 3, 12, '2026-03-10 20:22:51'),
(3, 5, '2', 1, 3, 0, '2026-03-10 20:25:09'),
(5, 4, 'test', 1, 3, 7, '2026-03-10 20:29:07');

-- --------------------------------------------------------
-- Table structure for table `reports`
-- --------------------------------------------------------

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `report_type` enum('Monthly Sales','Farmer Participation') DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `generated_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------
-- Table structure for table `weather_alerts`
-- --------------------------------------------------------

CREATE TABLE `weather_alerts` (
  `alert_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `severity` enum('Low','Medium','High') DEFAULT 'Low',
  `region` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`alert_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `weather_alerts` (`alert_id`, `title`, `message`, `severity`, `region`, `created_at`) VALUES
(3, 'Elevated Humidity Advisory', 'Humidity between 75–90%. Ensure good air circulation around crops.', 'Low', 'Quezon', '2026-03-12 09:46:06'),
(10, 'Elevated Humidity Advisory', 'Humidity between 75–90%. Ensure good air circulation around crops.', 'Low', 'Nueva Ecija', '2026-03-12 09:52:58');

-- --------------------------------------------------------
-- Table structure for table `weather_data`
-- --------------------------------------------------------

CREATE TABLE `weather_data` (
  `weather_id` int(11) NOT NULL AUTO_INCREMENT,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `humidity` int(11) DEFAULT NULL,
  `wind_speed` decimal(5,2) DEFAULT NULL,
  `data_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`weather_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------
-- Table structure for table `companies`
-- --------------------------------------------------------

CREATE TABLE `companies` (
  `company_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(100) NOT NULL,
  `company_address` varchar(255) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`company_id`),
  UNIQUE KEY `company_name_UNIQUE` (`company_name`),
  UNIQUE KEY `tax_id_UNIQUE` (`tax_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `companies` (`company_id`, `company_name`, `company_address`, `contact_person`, `tax_id`, `created_at`) VALUES
(1, 'Test', 'TEst', 'Test', '12341234', '2026-03-12 09:36:24');

-- --------------------------------------------------------
-- Table structure for table `company_buyers`
-- --------------------------------------------------------

CREATE TABLE `company_buyers` (
  `company_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `role_in_company` varchar(50) DEFAULT 'Employee',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`company_id`,`buyer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `company_buyers` (`company_id`, `buyer_id`, `role_in_company`, `joined_at`) VALUES
(1, 5, 'Employee', '2026-03-12 09:36:24');

-- --------------------------------------------------------
-- Table structure for table `cooperative_members`
-- --------------------------------------------------------

CREATE TABLE `cooperative_members` (
  `pool_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `quantity_contributed` decimal(10,2) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `cooperative_members` (`pool_id`, `farmer_id`, `quantity_contributed`) VALUES
(3, 7, 20.00),
(3, 6, 15.00);

-- --------------------------------------------------------
-- Table structure for table `cooperative_pools`
-- --------------------------------------------------------

CREATE TABLE `cooperative_pools` (
  `pool_id` int(11) NOT NULL AUTO_INCREMENT,
  `crop_id` int(11) NOT NULL,
  `total_quantity` decimal(10,2) DEFAULT NULL,
  `unit` enum('kg','g','pieces','sack','bundle') NOT NULL DEFAULT 'kg',
  `unit_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('Open','Closed','Archived') NOT NULL DEFAULT 'Open',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`pool_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `cooperative_pools` (`pool_id`, `crop_id`, `total_quantity`, `unit`, `unit_price`, `created_at`, `status`) VALUES
(3, 60, 0.00, 'kg', 75.00, '2026-03-12 11:29:19', 'Open');

-- --------------------------------------------------------
-- Triggers
-- --------------------------------------------------------

DELIMITER $$
CREATE TRIGGER `after_order_confirmed` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    IF NEW.order_status = 'Confirmed' AND OLD.order_status <> 'Confirmed' THEN
        IF NOT EXISTS (SELECT 1 FROM invoices WHERE order_id = NEW.order_id) THEN
            SET @invoice_num = CONCAT('INV-', YEAR(NOW()), LPAD(MONTH(NOW()),2,'0'), '-', LPAD(NEW.order_id,6,'0'));
            SET @subtotal = (
                SELECT COALESCE(SUM(
                    CASE
                        WHEN oi.inventory_id IS NOT NULL THEN COALESCE(ci.price,0) * oi.quantity
                        WHEN oi.pool_id IS NOT NULL THEN COALESCE(cp.unit_price,0) * oi.quantity
                        ELSE 0
                    END
                ),0)
                FROM order_items oi
                LEFT JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
                LEFT JOIN cooperative_pools cp ON oi.pool_id = cp.pool_id
                WHERE oi.order_id = NEW.order_id
            );
            SET @farmer_id = (
                SELECT COALESCE(ci.farmer_id, (SELECT cm.farmer_id FROM cooperative_members cm WHERE cm.pool_id = oi.pool_id LIMIT 1), NEW.buyer_id)
                FROM order_items oi
                LEFT JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id
                WHERE oi.order_id = NEW.order_id
                LIMIT 1
            );
            INSERT INTO invoices (invoice_number, order_id, buyer_id, farmer_id, subtotal, total_amount, due_date)
            VALUES (@invoice_num, NEW.order_id, NEW.buyer_id, @farmer_id, @subtotal, @subtotal, DATE_ADD(NOW(), INTERVAL 7 DAY));
            SET @new_invoice_id = LAST_INSERT_ID();
            INSERT INTO invoice_items (invoice_id, inventory_id, crop_name, quantity, unit_price, total_price)
            SELECT @new_invoice_id, oi.inventory_id, c.crop_name, oi.quantity, ci.price, ci.price * oi.quantity
            FROM order_items oi JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id JOIN crops c ON ci.crop_id = c.crop_id
            WHERE oi.order_id = NEW.order_id AND oi.inventory_id IS NOT NULL;
            INSERT INTO invoice_items (invoice_id, inventory_id, crop_name, quantity, unit_price, total_price)
            SELECT @new_invoice_id, NULL, c.crop_name, oi.quantity, COALESCE(cp.unit_price,0), COALESCE(cp.unit_price,0) * oi.quantity
            FROM order_items oi JOIN cooperative_pools cp ON oi.pool_id = cp.pool_id JOIN crops c ON cp.crop_id = c.crop_id
            WHERE oi.order_id = NEW.order_id AND oi.pool_id IS NOT NULL;
        END IF;
    END IF;
END$$
DELIMITER ;

-- --------------------------------------------------------
-- Final Indexes and Foreign Keys
-- --------------------------------------------------------

-- (Additional Indexes for performance)
ALTER TABLE `announcements` ADD KEY `created_by` (`created_by`), ADD KEY `idx_active` (`is_active`,`created_at`), ADD KEY `idx_audience` (`target_audience`), ADD KEY `idx_expires` (`expires_at`);
ALTER TABLE `announcement_views` ADD UNIQUE KEY `unique_view` (`announcement_id`,`user_id`), ADD KEY `idx_user_views` (`user_id`,`viewed_at`);
ALTER TABLE `cooperative_members` ADD KEY `fk_cooperative_members_cooperative_pools1_idx` (`pool_id`), ADD KEY `fk_cooperative_members_farmer_profiles1_idx` (`farmer_id`);
ALTER TABLE `cooperative_pools` ADD KEY `fk_cooperative_pools_crops1_idx` (`crop_id`);
ALTER TABLE `crops` ADD KEY `fk_crops_crop_categories_idx` (`category_id`);
ALTER TABLE `crops_inventory` ADD KEY `fk_crops_inventory_crops1_idx` (`crop_id`), ADD KEY `fk_crops_inventory_farmer_profiles1` (`farmer_id`), ADD KEY `idx_crops_inventory_farmer_harvestdate_status` (`farmer_id`,`harvest_date`,`harvest_status`);
ALTER TABLE `crop_images` ADD KEY `fk_crop_images_crops_inventory1_idx` (`inventory_id`);
ALTER TABLE `farm_images` ADD KEY `fk_farm_images_farmer_profiles_idx` (`farmer_id`);
ALTER TABLE `farmer_profiles` ADD KEY `fk_farmer_profiles_users_idx` (`farmer_id`);
ALTER TABLE `farmer_verification` ADD KEY `fk_farmer_verification_users1_idx` (`farmer_id`), ADD KEY `fk_farmer_verification_admin_idx` (`reviewed_by`);
ALTER TABLE `invoices` ADD KEY `order_id` (`order_id`), ADD KEY `idx_invoice_number` (`invoice_number`), ADD KEY `idx_buyer` (`buyer_id`), ADD KEY `idx_farmer` (`farmer_id`), ADD KEY `idx_payment_status` (`payment_status`), ADD KEY `idx_created_at` (`created_at`);
ALTER TABLE `invoice_items` ADD KEY `invoice_id` (`invoice_id`), ADD KEY `inventory_id` (`inventory_id`);
ALTER TABLE `notifications` ADD KEY `fk_notifications_users1_idx` (`user_id`);
ALTER TABLE `orders` ADD KEY `fk_orders_buyer_profiles1_idx` (`buyer_id`);
ALTER TABLE `order_items` ADD KEY `fk_order_items_orders1_idx` (`order_id`), ADD KEY `fk_order_items_crops_inventory1_idx` (`inventory_id`), ADD KEY `fk_order_items_cooperative_pools1_idx` (`pool_id`);
ALTER TABLE `payment` ADD KEY `fk_deliveries_orders11_idx` (`order_id`);
ALTER TABLE `ratings` ADD UNIQUE KEY `uniq_buyer_inventory` (`buyer_id`,`inventory_id`), ADD KEY `idx_ratings_farmer` (`farmer_id`), ADD KEY `idx_ratings_buyer` (`buyer_id`), ADD KEY `idx_ratings_inventory` (`inventory_id`);
ALTER TABLE `reports` ADD KEY `fk_reports_users1_idx` (`user_id`);
ALTER TABLE `weather_data` ADD KEY `idx_location` (`latitude`,`longitude`), ADD KEY `idx_date` (`created_at`);

-- --------------------------------------------------------
-- Constraints
-- --------------------------------------------------------

ALTER TABLE `announcements` ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `announcement_views` ADD CONSTRAINT `announcement_views_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`announcement_id`) ON DELETE CASCADE ON UPDATE CASCADE, ADD CONSTRAINT `announcement_views_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `buyer_profiles` ADD CONSTRAINT `fk_buyer_profiles_users1` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `company_buyers` ADD CONSTRAINT `fk_company_buyers_buyer_profiles` FOREIGN KEY (`buyer_id`) REFERENCES `buyer_profiles` (`buyer_id`) ON DELETE CASCADE ON UPDATE CASCADE, ADD CONSTRAINT `fk_company_buyers_companies` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `cooperative_members` ADD CONSTRAINT `fk_cooperative_members_cooperative_pools1` FOREIGN KEY (`pool_id`) REFERENCES `cooperative_pools` (`pool_id`) ON DELETE CASCADE ON UPDATE CASCADE, ADD CONSTRAINT `fk_cooperative_members_farmer_profiles1` FOREIGN KEY (`farmer_id`) REFERENCES `farmer_profiles` (`farmer_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `cooperative_pools` ADD CONSTRAINT `fk_cooperative_pools_crops1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`crop_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `crops` ADD CONSTRAINT `fk_crops_crop_categories` FOREIGN KEY (`category_id`) REFERENCES `crop_categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `crops_inventory` ADD CONSTRAINT `fk_crops_inventory_crops1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`crop_id`) ON DELETE CASCADE ON UPDATE CASCADE, ADD CONSTRAINT `fk_crops_inventory_farmer_profiles1` FOREIGN KEY (`farmer_id`) REFERENCES `farmer_profiles` (`farmer_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `crop_images` ADD CONSTRAINT `fk_crop_images_crops_inventory1` FOREIGN KEY (`inventory_id`) REFERENCES `crops_inventory` (`inventory_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `farm_images` ADD CONSTRAINT `fk_farm_images_farmer_profiles` FOREIGN KEY (`farmer_id`) REFERENCES `farmer_profiles` (`farmer_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `farmer_profiles` ADD CONSTRAINT `fk_farmer_profiles_users` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `farmer_verification` ADD CONSTRAINT `fk_farmer_verification_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE, ADD CONSTRAINT `fk_farmer_verification_users1` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `invoices` ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE, ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE, ADD CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `invoice_items` ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE, ADD CONSTRAINT `invoice_items_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `crops_inventory` (`inventory_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `notifications` ADD CONSTRAINT `fk_notifications_users1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `orders` ADD CONSTRAINT `fk_orders_buyer_profiles1` FOREIGN KEY (`buyer_id`) REFERENCES `buyer_profiles` (`buyer_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order_items` ADD CONSTRAINT `fk_order_items_cooperative_pools1` FOREIGN KEY (`pool_id`) REFERENCES `cooperative_pools` (`pool_id`) ON DELETE CASCADE ON UPDATE CASCADE, ADD CONSTRAINT `fk_order_items_crops_inventory1` FOREIGN KEY (`inventory_id`) REFERENCES `crops_inventory` (`inventory_id`) ON DELETE CASCADE ON UPDATE CASCADE, ADD CONSTRAINT `fk_order_items_orders1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `payment` ADD CONSTRAINT `fk_deliveries_orders10` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `reports` ADD CONSTRAINT `fk_reports_users1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
