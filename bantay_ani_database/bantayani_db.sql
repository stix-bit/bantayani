-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

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
  `user_id` INT NOT NULL AUTO_INCREMENT,
  `role` ENUM('Farmer', 'Guest', 'Buyer', 'Admin') NOT NULL,
  `first_name` VARCHAR(45) NOT NULL,
  `last_name` VARCHAR(45) NOT NULL,
  `email` VARCHAR(45) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(45) NULL,
  `address` VARCHAR(45) NOT NULL,
  `is_verified` TINYINT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `img_path` VARCHAR(255) NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE INDEX `email_UNIQUE` (`email` ASC) VISIBLE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bantayani_db`.`buyer_profiles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`buyer_profiles` (
  `buyer_id` INT NOT NULL,
  `company_name` VARCHAR(45) NULL,
  INDEX `fk_buyer_profiles_users1_idx` (`buyer_id` ASC) VISIBLE,
  PRIMARY KEY (`buyer_id`),
  CONSTRAINT `fk_buyer_profiles_users1`
    FOREIGN KEY (`buyer_id`)
    REFERENCES `bantayani_db`.`users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bantayani_db`.`farmer_profiles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`farmer_profiles` (
  `farmer_id` INT NOT NULL,
  `farm_name` VARCHAR(45) NOT NULL,
  `farm_location` VARCHAR(255) NOT NULL,
  `farm_img_path` VARCHAR(255) NULL,
  `verified_by` INT NULL,
  `verified_at` TIMESTAMP NULL,
  INDEX `fk_farmer_profiles_users_idx` (`farmer_id` ASC) VISIBLE,
  PRIMARY KEY (`farmer_id`),
  CONSTRAINT `fk_farmer_profiles_users`
    FOREIGN KEY (`farmer_id`)
    REFERENCES `bantayani_db`.`users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bantayani_db`.`crops`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`crops` (
  `crop_id` INT NOT NULL AUTO_INCREMENT,
  `crop_name` VARCHAR(45) NOT NULL,
  `unit` ENUM('kg', 'g', 'pieces') NOT NULL,
  PRIMARY KEY (`crop_id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bantayani_db`.`crops_inventory`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`crops_inventory` (
  `inventory_id` INT NOT NULL AUTO_INCREMENT,
  `farmer_id` INT NOT NULL,
  `crop_id` INT NOT NULL,
  `quantity` DECIMAL(10,2) NULL,
  `harvest_date` DATE NULL,
  `price` DECIMAL(10,2) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`inventory_id`),
  INDEX `fk_crops_inventory_crops1_idx` (`crop_id` ASC) VISIBLE,
  CONSTRAINT `fk_crops_inventory_farmer_profiles1`
    FOREIGN KEY (`farmer_id`)
    REFERENCES `bantayani_db`.`farmer_profiles` (`farmer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_crops_inventory_crops1`
    FOREIGN KEY (`crop_id`)
    REFERENCES `bantayani_db`.`crops` (`crop_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bantayani_db`.`cooperative_pools`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`cooperative_pools` (
  `pool_id` INT NOT NULL AUTO_INCREMENT,
  `crop_id` INT NOT NULL,
  `total_quantity` DECIMAL(10,2) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`pool_id`),
  INDEX `fk_cooperative_pools_crops1_idx` (`crop_id` ASC) VISIBLE,
  CONSTRAINT `fk_cooperative_pools_crops1`
    FOREIGN KEY (`crop_id`)
    REFERENCES `bantayani_db`.`crops` (`crop_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bantayani_db`.`cooperative_members`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`cooperative_members` (
  `pool_id` INT NOT NULL,
  `farmer_id` INT NOT NULL,
  `quantity_contributed` DECIMAL(10,2) NULL,
  INDEX `fk_cooperative_members_cooperative_pools1_idx` (`pool_id` ASC) VISIBLE,
  INDEX `fk_cooperative_members_farmer_profiles1_idx` (`farmer_id` ASC) VISIBLE,
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
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bantayani_db`.`orders`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`orders` (
  `order_id` INT NOT NULL AUTO_INCREMENT,
  `buyer_id` INT NOT NULL,
  `order_date` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `order_status` ENUM('Pending', 'Cancelled', 'Confirmed', 'Delivered', 'Shipped') NULL DEFAULT 'Pending',
  PRIMARY KEY (`order_id`),
  INDEX `fk_orders_buyer_profiles1_idx` (`buyer_id` ASC) VISIBLE,
  CONSTRAINT `fk_orders_buyer_profiles1`
    FOREIGN KEY (`buyer_id`)
    REFERENCES `bantayani_db`.`buyer_profiles` (`buyer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bantayani_db`.`order_items`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`order_items` (
  `order_item_id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `inventory_id` INT NULL,
  `pool_id` INT NULL,
  PRIMARY KEY (`order_item_id`),
  INDEX `fk_order_items_orders1_idx` (`order_id` ASC) VISIBLE,
  INDEX `fk_order_items_crops_inventory1_idx` (`inventory_id` ASC) VISIBLE,
  INDEX `fk_order_items_cooperative_pools1_idx` (`pool_id` ASC) VISIBLE,
  CONSTRAINT `fk_order_items_orders1`
    FOREIGN KEY (`order_id`)
    REFERENCES `bantayani_db`.`orders` (`order_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_crops_inventory1`
    FOREIGN KEY (`inventory_id`)
    REFERENCES `bantayani_db`.`crops_inventory` (`inventory_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_cooperative_pools1`
    FOREIGN KEY (`pool_id`)
    REFERENCES `bantayani_db`.`cooperative_pools` (`pool_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bantayani_db`.`deliveries`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`deliveries` (
  `delivery_id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `delivery_status` ENUM('Pending', 'Delivering', 'Delivered') NULL DEFAULT 'Pending',
  `delivery_date` DATE NULL,
  PRIMARY KEY (`delivery_id`),
  INDEX `fk_deliveries_orders1_idx` (`order_id` ASC) VISIBLE,
  CONSTRAINT `fk_deliveries_orders1`
    FOREIGN KEY (`order_id`)
    REFERENCES `bantayani_db`.`orders` (`order_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bantayani_db`.`notifications`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`notifications` (
  `notification_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `message` TEXT NULL,
  `notification_type` ENUM('Weather', 'Order', 'System') NULL,
  `is_read` TINYINT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  INDEX `fk_notifications_users1_idx` (`user_id` ASC) VISIBLE,
  CONSTRAINT `fk_notifications_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES `bantayani_db`.`users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bantayani_db`.`ratings`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`ratings` (
  `rating_id` INT NOT NULL AUTO_INCREMENT,
  `rating` INT NULL,
  `comment` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `farmer_id` INT NOT NULL,
  `buyer_id` INT NOT NULL,
  PRIMARY KEY (`rating_id`),
  INDEX `fk_ratings_farmer_profiles1_idx` (`farmer_id` ASC) VISIBLE,
  INDEX `fk_ratings_buyer_profiles1_idx` (`buyer_id` ASC) VISIBLE,
  CONSTRAINT `fk_ratings_farmer_profiles1`
    FOREIGN KEY (`farmer_id`)
    REFERENCES `bantayani_db`.`farmer_profiles` (`farmer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_ratings_buyer_profiles1`
    FOREIGN KEY (`buyer_id`)
    REFERENCES `bantayani_db`.`buyer_profiles` (`buyer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bantayani_db`.`reports`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`reports` (
  `report_id` INT NOT NULL AUTO_INCREMENT,
  `report_type` ENUM('Monthly Sales', 'Farmer Participation') NULL,
  `user_id` INT NOT NULL,
  `generated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`),
  INDEX `fk_reports_users1_idx` (`user_id` ASC) VISIBLE,
  CONSTRAINT `fk_reports_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES `bantayani_db`.`users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bantayani_db`.`payment`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bantayani_db`.`payment` (
  `payment_id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `payment_method` ENUM('Cash', 'Online') NULL DEFAULT 'Pending',
  `payment_status` ENUM('Pending', 'Paid', 'Cancelled') NULL,
  `payment_date` TIMESTAMP NULL,
  PRIMARY KEY (`payment_id`),
  INDEX `fk_deliveries_orders1_idx` (`order_id` ASC) VISIBLE,
  CONSTRAINT `fk_deliveries_orders10`
    FOREIGN KEY (`order_id`)
    REFERENCES `bantayani_db`.`orders` (`order_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
