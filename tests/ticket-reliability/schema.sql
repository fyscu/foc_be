-- Synthetic fixture schema from the 2026-09-07 documented production types.
-- Run ONLY in the throwaway foc_reliability_test database.
CREATE TABLE IF NOT EXISTS fy_users (
 id INT PRIMARY KEY,
 openid VARCHAR(255), access_token VARCHAR(100), token_expiry DATETIME,
 regtime DATETIME, nickname VARCHAR(255), realname VARCHAR(255), avatar VARCHAR(255),
 campus VARCHAR(10), role ENUM('user','technician','admin'),
 last_time DATETIME, available INT, wants VARCHAR(1) NOT NULL DEFAULT '1',
 max_concurrent INT NOT NULL DEFAULT 3, email VARCHAR(255), temp_email VARCHAR(255),
 email_status VARCHAR(255), phone VARCHAR(20), temp_phone VARCHAR(20),
 status VARCHAR(20), verification_code VARCHAR(25), immed TINYINT(1) NOT NULL DEFAULT 0,
 canDuo TINYINT, INDEX idx_users_openid (openid), INDEX idx_users_token (access_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS fy_workorders (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, user_nick VARCHAR(255), create_time DATETIME,
 machine_purchase_date DATE NOT NULL, user_phone VARCHAR(20) NOT NULL,
 device_type VARCHAR(50) NOT NULL, model VARCHAR(255),
 warranty_status ENUM('under','expired','unknown'), computer_brand VARCHAR(50) NOT NULL,
 repair_description TEXT NOT NULL, repair_status VARCHAR(20) NOT NULL,
 repair_image_url VARCHAR(255), fault_type VARCHAR(50) NOT NULL, qq_number VARCHAR(20),
 campus VARCHAR(50) NOT NULL, DuoCampus TINYINT, assigned_technician_id INT,
 assigned_time DATETIME, completion_time DATETIME, complete_image_url VARCHAR(255),
 order_hash VARCHAR(255), transcode INT, refused_times INT,
 archived TINYINT(1) NOT NULL DEFAULT 0, archived_at DATETIME,
 urgent TINYINT(1) NOT NULL DEFAULT 0, urgent_at DATETIME,
 restored_from BIGINT NULL,
 INDEX idx_workorders_assignee_status (assigned_technician_id,repair_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS fy_confs (
 id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(191) NOT NULL,
 info VARCHAR(255), data VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS fy_transfer_record (
 id INT AUTO_INCREMENT PRIMARY KEY, ticketid VARCHAR(20), type VARCHAR(255) NOT NULL,
 time DATETIME NOT NULL, fromuid INT NOT NULL, fromname VARCHAR(255),
 userid INT NOT NULL, username VARCHAR(255), tid INT NOT NULL, tname VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS fy_admins (
 id INT PRIMARY KEY, role VARCHAR(10) NOT NULL, username VARCHAR(191) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL, openid VARCHAR(191) NOT NULL UNIQUE,
 feishu_union_id VARCHAR(64), created_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS fy_admin_logs (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_username VARCHAR(64) NOT NULL,
 admin_openid VARCHAR(191) NOT NULL, admin_role VARCHAR(16) NOT NULL,
 action VARCHAR(32) NOT NULL, target_type VARCHAR(16) NOT NULL,
 target_id VARCHAR(64) NOT NULL, detail TEXT, ip VARCHAR(45) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_admin_logs_action (action), INDEX idx_admin_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS fy_admin_tokens (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_id INT NOT NULL,
 token CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL, ip VARCHAR(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS fy_app_tokens (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
 token VARCHAR(100) NOT NULL UNIQUE, expires_at DATETIME NOT NULL,
 created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
