-- ==============================================================================
-- MySQL Init Script: Pastikan database dan charset sudah dikonfigurasi dengan benar
-- Dieksekusi satu kali saat container MySQL pertama kali dibuat (fresh volume)
-- ==============================================================================

-- Set timezone ke UTC
SET GLOBAL time_zone = '+00:00';

-- Pastikan database tersedia dengan charset yang benar
-- (MySQL container sudah membuat DB dari MYSQL_DATABASE env, ini sebagai backup)
CREATE DATABASE IF NOT EXISTS `HateSpeech`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Grant user permission
GRANT ALL PRIVILEGES ON `HateSpeech`.* TO 'hatespeech_user'@'%';
FLUSH PRIVILEGES;
