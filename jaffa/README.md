# Notification Service API (PHP + MariaDB)

Student project component for the **Health Matters CMS system**.

A lightweight Notification Service API that lets other modules trigger notifications through a single HTTP endpoint.

---

## Features

- REST endpoint `/send`
- Health endpoint `/health`
- MariaDB / MySQL storage
- Template-based messages
- Mailgun-ready email
- SMS & Push (simulated)
- API key authentication
- Rate limiting
- UUID notification IDs
- Single-file deploy

---

## Endpoints

### Health

GET:

```
/index.php/health
```

Example:

```bash
https://vesta.uclan.ac.uk/~fatieh/jaffa/index.php/health
```

Response:

```json
{
  "status": "healthy",
  "timestamp": "2026-02-09T23:05:41+00:00",
  "service": "notification-service",
  "mailgun_configured": false
}
```

---

### Send Notification

POST:

```
/index.php/send
```

Example:

```bash
curl -X POST "https://vesta.uclan.ac.uk/~fatieh/jaffa/index.php/send" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: 7f3c9a4e8b1d2f6a0c5e3b9d7a2c1e8f4b6d0a3c9e1b" \
  -d '{
    "recipient_id": "11111111-1111-1111-1111-111111111111",
    "template_name": "welcome",
    "variable_data": {
      "name": "Fadi",
      "product": "Health Matters"
    }
  }'
```

Response:

```json
{
  "status": "success",
  "data": {
    "notification_id": "uuid",
    "event_type": "welcome",
    "channel": "Email",
    "user_id": "uuid",
    "status": "queued",
    "sent_at": null,
    "created_at": "ISO-8601 timestamp"
  }
}
```

---

## Authentication

All POST requests require:

```
X-API-Key: 7f3c9a4e8b1d2f6a0c5e3b9d7a2c1e8f4b6d0a3c9e1b
```

Configured via environment variable or config array.

---

## Database Schema

```sql
CREATE TABLE notifications (
  notification_id CHAR(36) PRIMARY KEY,
  event_type VARCHAR(100),
  channel VARCHAR(20),
  user_id CHAR(36),
  status VARCHAR(20),
  message TEXT,
  sent_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

```sql
CREATE TABLE recipient_preferences (
  recipient_id VARCHAR(255) PRIMARY KEY,
  email VARCHAR(255),
  preferred_channel VARCHAR(50) DEFAULT 'Email'
);
```

---

## Available Templates

The service uses named templates.  
Clients must provide a valid `template_name` and the required variables.

If a template does not exist, the API returns **404 Template not found**.

### welcome

**Variables required:**
- `name`
- `product`

**Message:**
```
Hi {{ name }}, welcome to {{ product }}!
```

---

### reset_password

**Variables required:**
- `name`
- `code`

**Message:**
```
Hello {{ name }}, reset your password using this code: {{ code }}
```

---

### invoice_ready

**Variables required:**
- `name`
- `invoice_id`
- `total`

**Message:**
```
Hi {{ name }}, your invoice {{ invoice_id }} is ready. Total: {{ total }}
```

---

### Adding Templates

Templates are defined in the configuration section:

```php
'templates' => [
  'example' => 'Your message with {{ variable }}'
]
```

No API changes are required to add new templates.

---

## Email

Mailgun supported via:

```
MAILGUN_API_KEY (TBC)
MAILGUN_DOMAIN (TBC)
```

If not configured, messages remain queued (demo mode).

---

## PostgreSQL Compatibility

Uses PDO. Switch by changing DSN:

```
pgsql:host=HOST;dbname=DB
```

No API changes required.