-- Yealink Phonebook Manager - DB Upgrade v2 -> v3
-- Compatible with MySQL 5.7
--
-- Run this inside your existing database:
--   mysql -u root -p yealink_phonebook < mysql_upgrade_v2_to_v3.sql
--
-- NOTE: This script is meant to be run ONCE.

-- 1) RBAC: add role to users
ALTER TABLE users
  ADD COLUMN role ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer' AFTER password_hash;

-- Existing users had full access before, so mark them as admin
UPDATE users SET role='admin';

-- 2) Revisions: store snapshot JSON for CSV export / rollback
ALTER TABLE revisions
  ADD COLUMN snapshot_json LONGTEXT NULL AFTER xml_sha256;

-- 3) Tags
CREATE TABLE IF NOT EXISTS tags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_tags (
  contact_id INT NOT NULL,
  tag_id INT NOT NULL,
  PRIMARY KEY (contact_id, tag_id),
  KEY idx_ct_tag (tag_id),
  CONSTRAINT fk_ct_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ct_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Settings
CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(64) NOT NULL,
  `value` LONGTEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (`key`, `value`) VALUES ('phonebook_ip_allowlist', '')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- 5) Audit log
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

-- Done.
