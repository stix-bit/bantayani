-- Harvest Scheduling additions for crops_inventory
-- Run this on bantayani_db

ALTER TABLE crops_inventory
    ADD COLUMN harvest_status ENUM('Scheduled','Confirmed','Cancelled') NOT NULL DEFAULT 'Scheduled' AFTER harvest_date,
    ADD COLUMN harvest_confirmed_at DATETIME NULL AFTER harvest_status,
    ADD COLUMN harvest_cancelled_at DATETIME NULL AFTER harvest_confirmed_at,
    ADD COLUMN harvest_notification_seen_at DATETIME NULL AFTER harvest_cancelled_at;

CREATE INDEX idx_crops_inventory_farmer_harvestdate_status
    ON crops_inventory (farmer_id, harvest_date, harvest_status);
