-- Migration script to update companies table structure
-- Run this script to update existing databases to the new structure

-- Step 1: Create temporary backup of existing companies data
CREATE TABLE IF NOT EXISTS companies_backup AS SELECT * FROM companies;

-- Step 2: Drop the old companies table
DROP TABLE IF EXISTS companies;

-- Step 3: Create the new companies table (independent of buyers)
CREATE TABLE IF NOT EXISTS `companies` (
  `company_id` INT NOT NULL AUTO_INCREMENT,
  `company_name` VARCHAR(100) NOT NULL,
  `company_address` VARCHAR(255) NULL,
  `contact_person` VARCHAR(100) NULL,
  `tax_id` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`),
  UNIQUE INDEX `company_name_UNIQUE` (`company_name` ASC),
  UNIQUE INDEX `tax_id_UNIQUE` (`tax_id` ASC)
) ENGINE=InnoDB;

-- Step 4: Create the new junction table
CREATE TABLE IF NOT EXISTS `company_buyers` (
  `company_id` INT NOT NULL,
  `buyer_id` INT NOT NULL,
  `role_in_company` VARCHAR(50) NULL DEFAULT 'Employee',
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`, `buyer_id`),
  INDEX `fk_company_buyers_companies_idx` (`company_id` ASC),
  INDEX `fk_company_buyers_buyer_profiles_idx` (`buyer_id` ASC),
  CONSTRAINT `fk_company_buyers_companies`
    FOREIGN KEY (`company_id`)
    REFERENCES `companies` (`company_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_company_buyers_buyer_profiles`
    FOREIGN KEY (`buyer_id`)
    REFERENCES `buyer_profiles` (`buyer_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Step 5: Migrate data from backup (removing duplicates)
-- This will keep the first occurrence of each company and link all buyers to it
INSERT IGNORE INTO companies (company_name, company_address, contact_person, tax_id, created_at)
SELECT DISTINCT company_name, company_address, contact_person, tax_id, created_at
FROM companies_backup
WHERE company_name IS NOT NULL AND company_name != '';

-- Step 6: Link buyers to their companies
INSERT INTO company_buyers (company_id, buyer_id, role_in_company)
SELECT c.company_id, cb.buyer_id, 'Employee'
FROM companies_backup cb
JOIN companies c ON c.company_name = cb.company_name AND c.tax_id = cb.tax_id
WHERE cb.buyer_id IS NOT NULL;

-- Step 7: Clean up (optional - comment out if you want to keep the backup)
-- DROP TABLE IF EXISTS companies_backup;
