-- -----------------------------------------------------
-- MySQL Workbench Forward Engineering
-- -----------------------------------------------------

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema bantayani_db
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `bantayani_db` DEFAULT CHARACTER SET utf8;
USE `bantayani_db`;

-- -----------------------------------------------------
-- Table `users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT NOT NULL AUTO_INCREMENT,
  `role` ENUM('Farmer', 'Buyer', 'Admin') NOT NULL,
  `first_name` VARCHAR(45) NOT NULL,
  `middle_name` VARCHAR(45) NOT NULL,
  `last_name` VARCHAR(45) NOT NULL,
  `email` VARCHAR(45) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(45) NULL,
  `address` VARCHAR(45) NOT NULL,
  `is_verified` TINYINT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `img_path` VARCHAR(255) NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE INDEX `email_UNIQUE` (`email` ASC)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `buyer_profiles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `buyer_profiles` (
  `buyer_id` INT NOT NULL,
  `preferred_payment_method` ENUM('Cash','Online') DEFAULT 'Cash',
  `verified` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`buyer_id`),
  CONSTRAINT `fk_buyer_profiles_users1`
    FOREIGN KEY (`buyer_id`)
    REFERENCES `users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `companies` (for company-specific info)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `companies` (
  `company_id` INT NOT NULL AUTO_INCREMENT,
  `buyer_id` INT NOT NULL,
  `company_name` VARCHAR(100) NOT NULL,
  `company_address` VARCHAR(255) NULL,
  `contact_person` VARCHAR(100) NULL,
  `tax_id` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`),
  INDEX `fk_companies_buyer_idx` (`buyer_id` ASC),
  CONSTRAINT `fk_companies_buyer`
    FOREIGN KEY (`buyer_id`)
    REFERENCES `buyer_profiles`(`buyer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `farmer_profiles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `farmer_profiles` (
  `farmer_id` INT NOT NULL,
  `farm_name` VARCHAR(45) NOT NULL,
  `farm_location` VARCHAR(255) NOT NULL,
  `farm_img_path` VARCHAR(255) NULL,
  `verified_by` INT NULL,
  `verified_at` TIMESTAMP NULL,
  INDEX `fk_farmer_profiles_users_idx` (`farmer_id` ASC),
  PRIMARY KEY (`farmer_id`),
  CONSTRAINT `fk_farmer_profiles_users`
    FOREIGN KEY (`farmer_id`)
    REFERENCES `users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `crops`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `crops` (
  `crop_id` INT NOT NULL AUTO_INCREMENT,
  `crop_name` VARCHAR(45) NOT NULL,
  `unit` ENUM('kg', 'g', 'pieces') NOT NULL,
  PRIMARY KEY (`crop_id`)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `crops_inventory`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `crops_inventory` (
  `inventory_id` INT NOT NULL AUTO_INCREMENT,
  `farmer_id` INT NOT NULL,
  `crop_id` INT NOT NULL,
  `quantity` DECIMAL(10,2) NULL,
  `harvest_date` DATE NULL,
  `price` DECIMAL(10,2) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`inventory_id`),
  INDEX `fk_crops_inventory_crops1_idx` (`crop_id` ASC),
  CONSTRAINT `fk_crops_inventory_farmer_profiles1`
    FOREIGN KEY (`farmer_id`)
    REFERENCES `farmer_profiles` (`farmer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_crops_inventory_crops1`
    FOREIGN KEY (`crop_id`)
    REFERENCES `crops` (`crop_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `cooperative_pools`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cooperative_pools` (
  `pool_id` INT NOT NULL AUTO_INCREMENT,
  `crop_id` INT NOT NULL,
  `total_quantity` DECIMAL(10,2) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`pool_id`),
  INDEX `fk_cooperative_pools_crops1_idx` (`crop_id` ASC),
  CONSTRAINT `fk_cooperative_pools_crops1`
    FOREIGN KEY (`crop_id`)
    REFERENCES `crops` (`crop_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `cooperative_members`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cooperative_members` (
  `pool_id` INT NOT NULL,
  `farmer_id` INT NOT NULL,
  `quantity_contributed` DECIMAL(10,2) NULL,
  INDEX `fk_cooperative_members_cooperative_pools1_idx` (`pool_id` ASC),
  INDEX `fk_cooperative_members_farmer_profiles1_idx` (`farmer_id` ASC),
  CONSTRAINT `fk_cooperative_members_cooperative_pools1`
    FOREIGN KEY (`pool_id`)
    REFERENCES `cooperative_pools` (`pool_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_cooperative_members_farmer_profiles1`
    FOREIGN KEY (`farmer_id`)
    REFERENCES `farmer_profiles` (`farmer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `orders`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` INT NOT NULL AUTO_INCREMENT,
  `buyer_id` INT NOT NULL,
  `order_date` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `order_status` ENUM('Pending', 'Cancelled', 'Confirmed', 'Delivered', 'Shipped') NULL DEFAULT 'Pending',
  PRIMARY KEY (`order_id`),
  INDEX `fk_orders_buyer_profiles1_idx` (`buyer_id` ASC),
  CONSTRAINT `fk_orders_buyer_profiles1`
    FOREIGN KEY (`buyer_id`)
    REFERENCES `buyer_profiles` (`buyer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `order_items`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `inventory_id` INT NULL,
  `pool_id` INT NULL,
  PRIMARY KEY (`order_item_id`),
  INDEX `fk_order_items_orders1_idx` (`order_id` ASC),
  INDEX `fk_order_items_crops_inventory1_idx` (`inventory_id` ASC),
  INDEX `fk_order_items_cooperative_pools1_idx` (`pool_id` ASC),
  CONSTRAINT `fk_order_items_orders1`
    FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`order_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_crops_inventory1`
    FOREIGN KEY (`inventory_id`)
    REFERENCES `crops_inventory` (`inventory_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_cooperative_pools1`
    FOREIGN KEY (`pool_id`)
    REFERENCES `cooperative_pools` (`pool_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `deliveries`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `deliveries` (
  `delivery_id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `delivery_status` ENUM('Pending', 'Delivering', 'Delivered') NULL DEFAULT 'Pending',
  `delivery_date` DATE NULL,
  PRIMARY KEY (`delivery_id`),
  INDEX `fk_deliveries_orders1_idx` (`order_id` ASC),
  CONSTRAINT `fk_deliveries_orders1`
    FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`order_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `notifications`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `message` TEXT NULL,
  `notification_type` ENUM('Weather', 'Order', 'System') NULL,
  `is_read` TINYINT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  INDEX `fk_notifications_users1_idx` (`user_id` ASC),
  CONSTRAINT `fk_notifications_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `ratings`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ratings` (
  `rating_id` INT NOT NULL AUTO_INCREMENT,
  `rating` INT NULL,
  `comment` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `farmer_id` INT NOT NULL,
  `buyer_id` INT NOT NULL,
  PRIMARY KEY (`rating_id`),
  INDEX `fk_ratings_farmer_profiles1_idx` (`farmer_id` ASC),
  INDEX `fk_ratings_buyer_profiles1_idx` (`buyer_id` ASC),
  CONSTRAINT `fk_ratings_farmer_profiles1`
    FOREIGN KEY (`farmer_id`)
    REFERENCES `farmer_profiles` (`farmer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_ratings_buyer_profiles1`
    FOREIGN KEY (`buyer_id`)
    REFERENCES `buyer_profiles` (`buyer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `reports`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `reports` (
  `report_id` INT NOT NULL AUTO_INCREMENT,
  `report_type` ENUM('Monthly Sales', 'Farmer Participation') NULL,
  `user_id` INT NOT NULL,
  `generated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`),
  INDEX `fk_reports_users1_idx` (`user_id` ASC),
  CONSTRAINT `fk_reports_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `payment`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment` (
  `payment_id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `payment_method` ENUM('Cash', 'Online') NULL,
  `payment_status` ENUM('Pending', 'Paid', 'Cancelled') NULL DEFAULT 'Pending',
  `payment_date` TIMESTAMP NULL,
  PRIMARY KEY (`payment_id`),
  INDEX `fk_deliveries_orders1_idx` (`order_id` ASC),
  CONSTRAINT `fk_deliveries_orders10`
    FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`order_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
