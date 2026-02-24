-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------
-- -----------------------------------------------------
-- Schema bantayani_db
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema bantayani_db
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `bantayani_db` DEFAULT CHARACTER SET utf8 ;
USE `bantayani_db` ;

-- -----------------------------------------------------
-- Table `bantayani_db`.`users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `role` ENUM('Farmer', 'Buyer', 'Admin') NOT NULL,
  `first_name` VARCHAR(45) NOT NULL,
  `middle_name` VARCHAR(45) NOT NULL,
  `last_name` VARCHAR(45) NOT NULL,
  `email` VARCHAR(45) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(45) NULL DEFAULT NULL,
  `address` VARCHAR(45) NOT NULL,
  `is_verified` TINYINT(4) NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  `img_path` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE INDEX `email_UNIQUE` (`email` ASC) )
ENGINE = InnoDB
AUTO_INCREMENT = 5
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`buyer_profiles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`buyer_profiles` (
  `buyer_id` INT(11) NOT NULL,
  `preferred_payment_method` ENUM('Cash', 'Online') NULL DEFAULT 'Cash',
  `verified` TINYINT(1) NULL DEFAULT 0,
  PRIMARY KEY (`buyer_id`),
  CONSTRAINT `fk_buyer_profiles_users1`
    FOREIGN KEY (`buyer_id`)
    REFERENCES `bantayani_db`.`users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`companies`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`companies` (
  `company_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_name` VARCHAR(100) NOT NULL,
  `company_address` VARCHAR(255) NULL DEFAULT NULL,
  `contact_person` VARCHAR(100) NULL DEFAULT NULL,
  `tax_id` VARCHAR(50) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`company_id`),
  UNIQUE INDEX `company_name_UNIQUE` (`company_name` ASC),
  UNIQUE INDEX `tax_id_UNIQUE` (`tax_id` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`company_buyers`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`company_buyers` (
  `company_id` INT(11) NOT NULL,
  `buyer_id` INT(11) NOT NULL,
  `role_in_company` VARCHAR(50) NULL DEFAULT 'Employee',
  `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`company_id`, `buyer_id`),
  INDEX `fk_company_buyers_companies_idx` (`company_id` ASC) ,
  INDEX `fk_company_buyers_buyer_profiles_idx` (`buyer_id` ASC) ,
  CONSTRAINT `fk_company_buyers_buyer_profiles`
    FOREIGN KEY (`buyer_id`)
    REFERENCES `bantayani_db`.`buyer_profiles` (`buyer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_company_buyers_companies`
    FOREIGN KEY (`company_id`)
    REFERENCES `bantayani_db`.`companies` (`company_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`crop_categories`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`crop_categories` (
  `category_id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  `display_order` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`category_id`))
ENGINE = InnoDB
AUTO_INCREMENT = 1
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`crops`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`crops` (
  `crop_id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) NOT NULL DEFAULT 8,
  `crop_name` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`crop_id`),
  INDEX `fk_crops_crop_categories_idx` (`category_id` ASC),
  CONSTRAINT `fk_crops_crop_categories`
    FOREIGN KEY (`category_id`)
    REFERENCES `bantayani_db`.`crop_categories` (`category_id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE)
ENGINE = InnoDB
AUTO_INCREMENT = 2
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`cooperative_pools`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`cooperative_pools` (
  `pool_id` INT(11) NOT NULL AUTO_INCREMENT,
  `crop_id` INT(11) NOT NULL,
  `total_quantity` DECIMAL(10,2) NULL DEFAULT NULL,
  `unit_price` DECIMAL(10,2) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`pool_id`),
  INDEX `fk_cooperative_pools_crops1_idx` (`crop_id` ASC) ,
  CONSTRAINT `fk_cooperative_pools_crops1`
    FOREIGN KEY (`crop_id`)
    REFERENCES `bantayani_db`.`crops` (`crop_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`farmer_profiles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`farmer_profiles` (
  `farmer_id` INT(11) NOT NULL,
  `farm_name` VARCHAR(45) NOT NULL,
  `farm_location` VARCHAR(255) NOT NULL,
  `farm_img_path` VARCHAR(255) NULL DEFAULT NULL,
  `verified_by` INT(11) NULL DEFAULT NULL,
  `verified_at` TIMESTAMP NULL DEFAULT NULL,
  `region` VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`farmer_id`),
  INDEX `fk_farmer_profiles_users_idx` (`farmer_id` ASC) ,
  CONSTRAINT `fk_farmer_profiles_users`
    FOREIGN KEY (`farmer_id`)
    REFERENCES `bantayani_db`.`users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`cooperative_members`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`cooperative_members` (
  `pool_id` INT(11) NOT NULL,
  `farmer_id` INT(11) NOT NULL,
  `quantity_contributed` DECIMAL(10,2) NULL DEFAULT NULL,
  INDEX `fk_cooperative_members_cooperative_pools1_idx` (`pool_id` ASC) ,
  INDEX `fk_cooperative_members_farmer_profiles1_idx` (`farmer_id` ASC) ,
  CONSTRAINT `fk_cooperative_members_cooperative_pools1`
    FOREIGN KEY (`pool_id`)
    REFERENCES `bantayani_db`.`cooperative_pools` (`pool_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_cooperative_members_farmer_profiles1`
    FOREIGN KEY (`farmer_id`)
    REFERENCES `bantayani_db`.`farmer_profiles` (`farmer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`crops_inventory`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`crops_inventory` (
  `inventory_id` INT(11) NOT NULL AUTO_INCREMENT,
  `farmer_id` INT(11) NOT NULL,
  `crop_id` INT(11) NOT NULL,
  `quantity` DECIMAL(10,2) NULL DEFAULT NULL,
  `unit` ENUM('kg', 'g', 'pieces', 'sack', 'bundle') NOT NULL DEFAULT 'kg',
  `harvest_date` DATE NULL DEFAULT NULL,
  `harvest_status` ENUM('Scheduled', 'Confirmed', 'Cancelled') NOT NULL DEFAULT 'Scheduled',
  `harvest_confirmed_at` DATETIME NULL DEFAULT NULL,
  `harvest_cancelled_at` DATETIME NULL DEFAULT NULL,
  `harvest_notification_seen_at` DATETIME NULL DEFAULT NULL,
  `price` DECIMAL(10,2) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`inventory_id`),
  INDEX `fk_crops_inventory_crops1_idx` (`crop_id` ASC) ,
  INDEX `fk_crops_inventory_farmer_profiles1` (`farmer_id` ASC) ,
  CONSTRAINT `fk_crops_inventory_crops1`
    FOREIGN KEY (`crop_id`)
    REFERENCES `bantayani_db`.`crops` (`crop_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_crops_inventory_farmer_profiles1`
    FOREIGN KEY (`farmer_id`)
    REFERENCES `bantayani_db`.`farmer_profiles` (`farmer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
AUTO_INCREMENT = 2
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`crop_images`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`crop_images` (
  `image_id` INT(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` INT(11) NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`image_id`),
  INDEX `fk_crop_images_crops_inventory1_idx` (`inventory_id` ASC),
  CONSTRAINT `fk_crop_images_crops_inventory1`
    FOREIGN KEY (`inventory_id`)
    REFERENCES `bantayani_db`.`crops_inventory` (`inventory_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`farmer_verification`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`farmer_verification` (
  `verification_id` INT(11) NOT NULL AUTO_INCREMENT,
  `farmer_id` INT(11) NOT NULL,
  `certificate_type` ENUM('Business Permit', 'Agricultural License', 'Tax Identification', 'Others') NOT NULL,
  `certificate_name` VARCHAR(255) NOT NULL,
  `certificate_path` VARCHAR(255) NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
  `submitted_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  `reviewed_by` INT(11) NULL DEFAULT NULL,
  `admin_notes` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`verification_id`),
  INDEX `fk_farmer_verification_users1_idx` (`farmer_id` ASC) ,
  INDEX `fk_farmer_verification_admin_idx` (`reviewed_by` ASC) ,
  CONSTRAINT `fk_farmer_verification_admin`
    FOREIGN KEY (`reviewed_by`)
    REFERENCES `bantayani_db`.`users` (`user_id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_farmer_verification_users1`
    FOREIGN KEY (`farmer_id`)
    REFERENCES `bantayani_db`.`users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
AUTO_INCREMENT = 2
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`notifications`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`notifications` (
  `notification_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL DEFAULT NULL,
  `message` TEXT NULL DEFAULT NULL,
  `notification_type` ENUM('Weather', 'Order', 'System') NULL DEFAULT NULL,
  `is_read` TINYINT(4) NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`notification_id`),
  INDEX `fk_notifications_users1_idx` (`user_id` ASC) ,
  CONSTRAINT `fk_notifications_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES `bantayani_db`.`users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`orders`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`orders` (
  `order_id` INT(11) NOT NULL AUTO_INCREMENT,
  `buyer_id` INT(11) NOT NULL,
  `order_date` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  `order_status` ENUM('Pending', 'Cancelled', 'Confirmed', 'Delivered', 'Shipped') NULL DEFAULT 'Pending',
  PRIMARY KEY (`order_id`),
  INDEX `fk_orders_buyer_profiles1_idx` (`buyer_id` ASC) ,
  CONSTRAINT `fk_orders_buyer_profiles1`
    FOREIGN KEY (`buyer_id`)
    REFERENCES `bantayani_db`.`buyer_profiles` (`buyer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
AUTO_INCREMENT = 14
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`order_items`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`order_items` (
  `order_item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `inventory_id` INT(11) NULL DEFAULT NULL,
  `pool_id` INT(11) NULL DEFAULT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1,
  PRIMARY KEY (`order_item_id`),
  INDEX `fk_order_items_orders1_idx` (`order_id` ASC) ,
  INDEX `fk_order_items_crops_inventory1_idx` (`inventory_id` ASC) ,
  INDEX `fk_order_items_cooperative_pools1_idx` (`pool_id` ASC) ,
  CONSTRAINT `fk_order_items_cooperative_pools1`
    FOREIGN KEY (`pool_id`)
    REFERENCES `bantayani_db`.`cooperative_pools` (`pool_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_crops_inventory1`
    FOREIGN KEY (`inventory_id`)
    REFERENCES `bantayani_db`.`crops_inventory` (`inventory_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_orders1`
    FOREIGN KEY (`order_id`)
    REFERENCES `bantayani_db`.`orders` (`order_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
AUTO_INCREMENT = 10
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`payment`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`payment` (
  `payment_id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `payment_method` ENUM('Cash', 'Online') NULL DEFAULT NULL,
  `payment_status` ENUM('Pending', 'Paid', 'Cancelled') NULL DEFAULT 'Pending',
  `payment_date` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  INDEX `fk_deliveries_orders1_idx` (`order_id` ASC) ,
  CONSTRAINT `fk_deliveries_orders10`
    FOREIGN KEY (`order_id`)
    REFERENCES `bantayani_db`.`orders` (`order_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
AUTO_INCREMENT = 14
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`ratings`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`ratings` (
  `rating_id` INT(11) NOT NULL AUTO_INCREMENT,
  `rating` INT(11) NULL DEFAULT NULL,
  `comment` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  `farmer_id` INT(11) NOT NULL,
  `buyer_id` INT(11) NOT NULL,
  PRIMARY KEY (`rating_id`),
  INDEX `fk_ratings_farmer_profiles1_idx` (`farmer_id` ASC) ,
  INDEX `fk_ratings_buyer_profiles1_idx` (`buyer_id` ASC) ,
  CONSTRAINT `fk_ratings_buyer_profiles1`
    FOREIGN KEY (`buyer_id`)
    REFERENCES `bantayani_db`.`buyer_profiles` (`buyer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_ratings_farmer_profiles1`
    FOREIGN KEY (`farmer_id`)
    REFERENCES `bantayani_db`.`farmer_profiles` (`farmer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`reports`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`reports` (
  `report_id` INT(11) NOT NULL AUTO_INCREMENT,
  `report_type` ENUM('Monthly Sales', 'Farmer Participation') NULL DEFAULT NULL,
  `user_id` INT(11) NOT NULL,
  `generated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`report_id`),
  INDEX `fk_reports_users1_idx` (`user_id` ASC) ,
  CONSTRAINT `fk_reports_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES `bantayani_db`.`users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bantayani_db`.`weather_alerts`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`weather_alerts` (
  `alert_id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `severity` ENUM('Low', 'Medium', 'High') NULL DEFAULT 'Low',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`alert_id`))
ENGINE = InnoDB
AUTO_INCREMENT = 7
DEFAULT CHARACTER SET = utf8;


-- Sample weather alerts data
INSERT INTO weather_alerts (title, message, severity) VALUES
('Heavy Rain Warning', 'Heavy rainfall expected. Consider early harvest.', 'High'),
('Hot Weather', 'High temperature may affect crops. Water regularly.', 'Medium');


-- -----------------------------------------------------
-- Table `bantayani_db`.`weather_data`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`weather_data` (
  `weather_id` INT(11) NOT NULL AUTO_INCREMENT,
  `latitude` DECIMAL(10,8) NOT NULL,
  `longitude` DECIMAL(11,8) NOT NULL,
  `temperature` DECIMAL(5,2) NULL DEFAULT NULL,
  `humidity` INT(11) NULL DEFAULT NULL,
  `wind_speed` DECIMAL(5,2) NULL DEFAULT NULL,
  `data_json` LONGTEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`weather_id`),
  INDEX `idx_location` (`latitude` ASC, `longitude` ASC) ,
  INDEX `idx_date` (`created_at` ASC) )
ENGINE = InnoDB
AUTO_INCREMENT = 22
DEFAULT CHARACTER SET = utf8;

-- Crop Categories Data
INSERT INTO crop_categories (category_id, category_name, display_order) VALUES
(1, 'Cereals & Grains', 1),
(2, 'Fruits', 2),
(3, 'Vegetables', 3),
(4, 'Root Crops', 4),
(5, 'Legumes', 5),
(6, 'Plantation / Industrial Crops', 6),
(7, 'Herbs & Spices', 7),
(8, 'Other Local Crops', 8);


-- Cereals & Grains Data
INSERT INTO crops (category_id, crop_name) VALUES
(1, 'Rice'),
(1, 'Corn'),
(1, 'Millet'),
(1, 'Other local grains'),

-- Fruits Data
(2, 'Banana'),
(2, 'Mango'),
(2, 'Pineapple'),
(2, 'Papaya'),
(2, 'Calamansi'),
(2, 'Durian'),
(2, 'Lanzones'),
(2, 'Other fruits'),

-- Vegetables Data
(3, 'Leafy vegetables'),
(3, 'Fruiting vegetables'),
(3, 'Root vegetables'),

-- Root Crops Data
(4, 'Cassava'),
(4, 'Sweet potato'),
(4, 'Taro'),
(4, 'Ube'),
(4, 'Yam variants'),

-- Legumes Data
(5, 'Mung bean'),
(5, 'Peanut'),
(5, 'Soybean'),
(5, 'Other beans'),

-- Plantation / Industrial Crops Data
(6, 'Coconut'),
(6, 'Sugarcane'),
(6, 'Coffee'),
(6, 'Rubber'),
(6, 'Abaca'),

-- Herbs & Spices Data
(7, 'Ginger'),
(7, 'Garlic'),
(7, 'Onion'),
(7, 'Chili'),
(7, 'Other herbs'),

-- Other Local Crops Data
(8, 'Farmers can specify if not listed above');


-- Additional indexes for performance optimization
CREATE INDEX idx_crops_inventory_farmer_harvestdate_status ON crops_inventory (farmer_id, harvest_date, harvest_status);


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
