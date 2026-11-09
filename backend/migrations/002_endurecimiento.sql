CREATE TABLE audit_log (
  id {{PK}}, user_id INTEGER REFERENCES users(id) ON DELETE SET NULL, user_name VARCHAR(120),
  action VARCHAR(40) NOT NULL, detail VARCHAR(255), ip VARCHAR(45), created_at VARCHAR(25) NOT NULL
);
CREATE INDEX idx_audit_created ON audit_log(created_at);
CREATE UNIQUE INDEX idx_tickets_alerta_activa ON tickets(device_id, alert_key) WHERE alert_key IS NOT NULL AND status IN ('abierto','en_curso')
