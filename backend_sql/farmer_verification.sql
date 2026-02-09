-- Farmer Verification Table
-- This table stores certificates uploaded by farmers for admin verification

CREATE TABLE IF NOT EXISTS `bantayani_db`.`farmer_verification` (
  `verification_id` INT NOT NULL AUTO_INCREMENT,
  `farmer_id` INT NOT NULL,
  `certificate_type` ENUM('Business Permit', 'Agricultural License', 'Tax Identification', 'Others') NOT NULL,
  `certificate_name` VARCHAR(255) NOT NULL,
  `certificate_path` VARCHAR(255) NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
  `submitted_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` TIMESTAMP NULL,
  `reviewed_by` INT NULL,
  `admin_notes` TEXT NULL,
  PRIMARY KEY (`verification_id`),
  INDEX `fk_farmer_verification_users1_idx` (`farmer_id` ASC) ,
  INDEX `fk_farmer_verification_admin_idx` (`reviewed_by` ASC) ,
  CONSTRAINT `fk_farmer_verification_users1`
    FOREIGN KEY (`farmer_id`)
    REFERENCES `bantayani_db`.`users` (`user_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_farmer_verification_admin`
    FOREIGN KEY (`reviewed_by`)
    REFERENCES `bantayani_db`.`users` (`user_id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;
