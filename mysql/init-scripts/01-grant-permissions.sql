-- Check if the user already exists and drop it if it does
DROP USER IF EXISTS 'nusantara_booking'@'%';

-- Create the user
CREATE USER 'nusantara_booking'@'%' IDENTIFIED BY 'nusantara_booking123';

-- Grant all privileges to the user
GRANT ALL PRIVILEGES ON *.* TO 'nusantara_booking'@'%' WITH GRANT OPTION;

-- Reload the privilege tables
FLUSH PRIVILEGES;
