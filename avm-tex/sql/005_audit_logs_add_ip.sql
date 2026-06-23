-- Add IP address tracking to audit_logs for audit log management display
ALTER TABLE audit_logs
    ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER record_id,
    ADD KEY idx_audit_ip (ip_address);
