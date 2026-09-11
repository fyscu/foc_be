-- Non-unique indexes for the current FOC v1 hot queries.
-- Existing duplicate identities and orphaned work orders are intentionally untouched.

ALTER TABLE fy_users
    ADD INDEX idx_users_openid (openid),
    ADD INDEX idx_users_access_token_expiry (access_token, token_expiry),
    ADD INDEX idx_users_phone (phone),
    ADD INDEX idx_users_email (email),
    ADD INDEX idx_users_tech_pool (role, immed, wants, campus),
    ALGORITHM=INPLACE,
    LOCK=NONE;

ALTER TABLE fy_workorders
    ADD INDEX idx_workorders_user_status (user_id, repair_status),
    ADD INDEX idx_workorders_campus_status_urgent (campus, repair_status, urgent),
    ADD INDEX idx_workorders_tech_status (assigned_technician_id, repair_status),
    ADD INDEX idx_workorders_status_tech (repair_status, assigned_technician_id),
    ADD INDEX idx_workorders_pending_priority
        (repair_status, urgent DESC, urgent_at, create_time, id),
    ADD INDEX idx_workorders_status_created_tech
        (repair_status, create_time, assigned_technician_id),
    ADD INDEX idx_workorders_order_hash (order_hash),
    ALGORITHM=INPLACE,
    LOCK=NONE;

ALTER TABLE fy_transfer_record
    ADD INDEX idx_transfer_ticket_time (ticketid, time),
    ALGORITHM=INPLACE,
    LOCK=NONE;

-- Manual rollback, only when query-plan validation shows a regression:
-- ALTER TABLE fy_users
--     DROP INDEX idx_users_openid,
--     DROP INDEX idx_users_access_token_expiry,
--     DROP INDEX idx_users_phone,
--     DROP INDEX idx_users_email,
--     DROP INDEX idx_users_tech_pool,
--     ALGORITHM=INPLACE, LOCK=NONE;
-- ALTER TABLE fy_workorders
--     DROP INDEX idx_workorders_user_status,
--     DROP INDEX idx_workorders_campus_status_urgent,
--     DROP INDEX idx_workorders_tech_status,
--     DROP INDEX idx_workorders_status_tech,
--     DROP INDEX idx_workorders_pending_priority,
--     DROP INDEX idx_workorders_status_created_tech,
--     DROP INDEX idx_workorders_order_hash,
--     ALGORITHM=INPLACE, LOCK=NONE;
-- ALTER TABLE fy_transfer_record
--     DROP INDEX idx_transfer_ticket_time,
--     ALGORITHM=INPLACE, LOCK=NONE;
