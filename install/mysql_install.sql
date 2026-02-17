-- Yealink Phonebook Manager - MySQL 5.7 Install Script (NO PREPARE)
--
-- Usage (as MySQL root):
--   mysql -u root -p < mysql_install.sql
--
-- IMPORTANT:
--  1) Edit DB name / user / password below
--  2) Change the initial admin password after first login (UI: Account -> Passwort ändern)

-- ----------------------
-- EDIT HERE
-- ----------------------

-- Database
CREATE DATABASE IF NOT EXISTS yealink_phonebook
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- DB user (localhost only)
CREATE USER IF NOT EXISTS 'yealink_phonebook'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES
  ON yealink_phonebook.*
  TO 'yealink_phonebook'@'localhost';
FLUSH PRIVILEGES;

USE yealink_phonebook;

-- ----------------------
-- Tables
-- ----------------------

-- Users (RBAC)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contacts
CREATE TABLE IF NOT EXISTS contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  department VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_contacts_name (name),
  KEY idx_contacts_department (department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contact Numbers (multiple per contact)
CREATE TABLE IF NOT EXISTS contact_numbers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contact_id INT NOT NULL,
  label VARCHAR(64) NOT NULL,
  number VARCHAR(64) NOT NULL,
  sort_order INT NOT NULL DEFAULT 1,
  KEY idx_numbers_contact (contact_id),
  KEY idx_numbers_number (number),
  CONSTRAINT fk_numbers_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tags
CREATE TABLE IF NOT EXISTS tags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contact <-> Tags
CREATE TABLE IF NOT EXISTS contact_tags (
  contact_id INT NOT NULL,
  tag_id INT NOT NULL,
  PRIMARY KEY (contact_id, tag_id),
  KEY idx_ct_tag (tag_id),
  CONSTRAINT fk_ct_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ct_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Revisions
CREATE TABLE IF NOT EXISTS revisions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  revision_number INT NOT NULL,
  comment VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  active TINYINT(1) NOT NULL DEFAULT 0,
  format VARCHAR(32) NOT NULL DEFAULT 'yealink',
  xml LONGTEXT NOT NULL,
  xml_sha256 CHAR(64) NOT NULL,
  snapshot_json LONGTEXT NULL,
  UNIQUE KEY uniq_rev_number (revision_number),
  KEY idx_rev_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings (key/value)
CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(64) NOT NULL,
  `value` LONGTEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit Log
CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  entity_type VARCHAR(50) NULL,
  entity_id INT NULL,
  details TEXT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  KEY idx_audit_created_at (created_at),
  KEY idx_audit_user (user_id),
  KEY idx_audit_entity (entity_type, entity_id),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------
-- Seed initial admin
-- ----------------------

-- bcrypt hash for password: admin
-- You can generate your own with:
--   php -r 'echo password_hash("mysecret", PASSWORD_DEFAULT), "\n";'
INSERT INTO users (username, password_hash, role)
VALUES ('admin', '$2y$10$vpO1BZ1/emNByM9IS/homOp4RbFn1VhtCXquEiHfpLUO6ojvh2uoS', 'admin')
ON DUPLICATE KEY UPDATE role='admin';

-- Default: allow all phonebook IPs (empty allowlist)
INSERT INTO settings (`key`, `value`) VALUES ('phonebook_ip_allowlist', '')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- Done.
