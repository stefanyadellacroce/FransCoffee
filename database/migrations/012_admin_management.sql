USE franscoffee;

-- Phase 12: Admin management and editable system settings.
-- This migration is safe for an existing Phase 11 database and does not delete business data.

INSERT INTO settings (setting_key,setting_value) VALUES
('business_days','Monday–Sunday'),
('opening_time','07:00'),
('closing_time','21:00'),
('logo_path','')
ON DUPLICATE KEY UPDATE setting_value=setting_value;
