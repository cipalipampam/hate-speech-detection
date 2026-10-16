SET GLOBAL time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `HateSpeech`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `HateSpeech`.* TO 'hatespeech_user'@'%';
FLUSH PRIVILEGES;
