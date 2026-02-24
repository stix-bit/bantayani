-- Add unit column to cooperative_pools table
ALTER TABLE `bantayani_db`.`cooperative_pools` 
ADD COLUMN `unit` ENUM('kg', 'g', 'pieces', 'sack', 'bundle') NOT NULL DEFAULT 'kg' AFTER `total_quantity`;


ALTER TABLE crops
ADD COLUMN unit ENUM('kg','g','pieces','sack','bundle')
NOT NULL DEFAULT 'kg';