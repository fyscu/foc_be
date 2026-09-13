CREATE TABLE IF NOT EXISTS fy_ticket_notification_outbox (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_id CHAR(32) CHARACTER SET ascii NOT NULL,
 job_key VARCHAR(40) CHARACTER SET ascii NOT NULL,
 ticket_id BIGINT NOT NULL,
 kind VARCHAR(20) CHARACTER SET ascii NOT NULL,
 channel VARCHAR(10) CHARACTER SET ascii NOT NULL,
 payload JSON NOT NULL,
 status VARCHAR(16) CHARACTER SET ascii NOT NULL DEFAULT 'pending',
 attempts INT NOT NULL DEFAULT 0,
 available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 lease_until DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 completed_at DATETIME NULL,
 last_error VARCHAR(255) NULL,
 UNIQUE KEY uk_ticket_event_job (event_id, job_key),
 KEY idx_ticket_outbox_ready (status, available_at),
 KEY idx_ticket_outbox_lease (status, lease_until),
 KEY idx_ticket_outbox_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS fy_ticket_action_receipts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 ticket_id BIGINT NOT NULL,
 actor_id INT NOT NULL,
 request_digest CHAR(64) CHARACTER SET ascii NOT NULL,
 request_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 resulting_code INT NOT NULL,
 result_json JSON NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_ticket_receipt (ticket_id, actor_id, request_digest, created_at),
 UNIQUE KEY uk_ticket_request (actor_id, request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
