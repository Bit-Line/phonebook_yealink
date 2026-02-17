-- Yealink Phonebook Manager - Uninstall helper (MySQL 5.7+)
-- WARNING: Drops the database and the user.
--
-- Passe die Namen unten an, falls du DB/User anders genannt hast.

DROP DATABASE IF EXISTS `yealink_phonebook`;
DROP USER IF EXISTS 'yealink_pb'@'localhost';
FLUSH PRIVILEGES;
