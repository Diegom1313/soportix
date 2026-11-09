CREATE TABLE users (
  id {{PK}}, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL, role VARCHAR(10) NOT NULL CHECK (role IN ('admin','tecnico','usuario')),
  active INTEGER NOT NULL DEFAULT 1, created_at VARCHAR(25) NOT NULL
);
CREATE TABLE api_tokens (
  id {{PK}}, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash CHAR(64) NOT NULL UNIQUE, expires_at VARCHAR(25) NOT NULL
);
CREATE TABLE login_attempts (
  id {{PK}}, identifier CHAR(64) NOT NULL, attempted_at VARCHAR(25) NOT NULL
);
CREATE INDEX idx_login_attempts ON login_attempts(identifier, attempted_at);
CREATE TABLE devices (
  id {{PK}}, hostname VARCHAR(190) NOT NULL UNIQUE, agent_key_hash CHAR(64) NOT NULL UNIQUE,
  os VARCHAR(190), cpu VARCHAR(190), ram_mb INTEGER, disk_total_gb INTEGER, disk_used_pct INTEGER,
  ip VARCHAR(45), software TEXT, last_seen VARCHAR(25), created_at VARCHAR(25) NOT NULL
);
CREATE TABLE device_reports (
  id {{PK}}, device_id INTEGER NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
  disk_used_pct INTEGER, reported_at VARCHAR(25) NOT NULL
);
CREATE INDEX idx_reports_device ON device_reports(device_id, reported_at);
CREATE TABLE tickets (
  id {{PK}}, title VARCHAR(160) NOT NULL, description TEXT NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'abierto' CHECK (status IN ('abierto','en_curso','resuelto','cerrado')),
  priority VARCHAR(8) NOT NULL DEFAULT 'media' CHECK (priority IN ('baja','media','alta','critica')),
  source VARCHAR(8) NOT NULL DEFAULT 'manual' CHECK (source IN ('manual','agente')),
  alert_key VARCHAR(60),
  requester_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  assignee_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  device_id INTEGER REFERENCES devices(id) ON DELETE SET NULL,
  due_at VARCHAR(25) NOT NULL, resolved_at VARCHAR(25),
  created_at VARCHAR(25) NOT NULL, updated_at VARCHAR(25) NOT NULL
);
CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_requester ON tickets(requester_id);
CREATE TABLE ticket_comments (
  id {{PK}}, ticket_id INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  body TEXT NOT NULL, internal INTEGER NOT NULL DEFAULT 0, created_at VARCHAR(25) NOT NULL
);
CREATE INDEX idx_comments_ticket ON ticket_comments(ticket_id);
