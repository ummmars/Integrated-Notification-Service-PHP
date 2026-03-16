-- =============================================================================
-- Notification Service - Database Schema
-- MariaDB / MySQL
-- =============================================================================

CREATE TABLE IF NOT EXISTS notifications (
  notification_id CHAR(36)     NOT NULL,
  event_type      VARCHAR(100) NOT NULL,
  channel         VARCHAR(20)  NOT NULL,
  user_id         CHAR(36)     NOT NULL,
  status          VARCHAR(20)  NOT NULL DEFAULT 'queued',
  message         TEXT         NOT NULL,
  sent_at         DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notification_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recipient_preferences (
  id                INT          NOT NULL AUTO_INCREMENT,
  recipient_id      VARCHAR(255) NOT NULL,
  email             VARCHAR(255) NULL,
  preferred_channel VARCHAR(50)  NOT NULL DEFAULT 'Email',
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recipient_id (recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_recipient_preferences_recipient_id
  ON recipient_preferences (recipient_id);

CREATE TABLE IF NOT EXISTS audit_log (
  id            INT          NOT NULL AUTO_INCREMENT,
  recipient_id  VARCHAR(255) NOT NULL,
  template_name VARCHAR(100) NOT NULL,
  channel       VARCHAR(50)  NOT NULL,
  message       TEXT         NOT NULL,
  status        VARCHAR(50)  NOT NULL,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_audit_log_recipient_id ON audit_log (recipient_id);
CREATE INDEX idx_audit_log_created_at   ON audit_log (created_at);
CREATE INDEX idx_audit_log_status       ON audit_log (status);

INSERT IGNORE INTO recipient_preferences (recipient_id, email, preferred_channel) VALUES
  ('11111111-1111-1111-1111-111111111111', 'test@example.com', 'Email'),
  ('user123', 'test@example.com', 'Email'),
  ('user456', 'john@example.com', 'SMS'),
  ('user789', 'jane@example.com', 'Push');
F
cat > composer.json << 'EOF'
{
    "name": "fadi/notifications-service-php",
    "description": "Lightweight PHP notification service API with Mailgun email, SMS and push support",
    "type": "project",
    "license": "MIT",
    "require": {
        "php": ">=8.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10"
    }
}