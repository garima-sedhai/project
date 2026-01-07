-- Add otp column if it doesn't exist
SET @otp_exists = (SELECT COUNT(*) FROM information_schema.columns 
                  WHERE table_name = 'users' AND column_name = 'otp' 
                  AND table_schema = DATABASE());

SET @sql = IF(@otp_exists = 0, 
              'ALTER TABLE users ADD COLUMN otp VARCHAR(10) AFTER registration_status', 
              'SELECT "otp column already exists" as Status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add otp_expiry column if it doesn't exist
SET @otp_expiry_exists = (SELECT COUNT(*) FROM information_schema.columns 
                         WHERE table_name = 'users' AND column_name = 'otp_expiry' 
                         AND table_schema = DATABASE());

SET @sql = IF(@otp_expiry_exists = 0, 
              'ALTER TABLE users ADD COLUMN otp_expiry DATETIME AFTER otp', 
              'SELECT "otp_expiry column already exists" as Status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;