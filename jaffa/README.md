# Health Matters Notification Service

## PHP + MariaDB Notification System

Student project component for the Health Matters CMS system.

This project implements a Notification Service that allows different parts of a health management system to trigger notifications for users. The service provides both a REST API backend and a web-based frontend interface to demonstrate notifications such as reminders, achievements, and alerts.

The system stores notifications in a MariaDB database, supports template-based messages, and simulates delivery through Email, SMS, and Push channels.

---

## System Overview

The notification system consists of three main components:

- Backend Notification API

- Frontend Notification Interface

- Database Storage

These components work together to allow notifications to be generated, displayed to the user, and stored in the database for history tracking.

---

## Features

### Backend API

- REST endpoint for sending notifications `/send`

- Health check endpoint `/health`

- Template-based notification messages

- API key authentication

- Rate limiting protection

- UUID notification IDs

- Mailgun email support (optional)

- SMS and Push notifications simulated

---

### Frontend Interface

- User login page

- Interactive notification dashboard

- Notification history viewer

- Clear notification history

- Demonstration buttons for different notification types

- Real-time popup notifications

- Stack notification demonstration (multiple notifications)

---

### Notification Types Implemented:

- Welcome notification

- Medication reminders

- Appointment reminders

- Healthy habit reminders

- Goal achieved notifications

- Missed activity alerts

- Referral confirmation notifications

---

## Project Structure

```
jaffa/

│

├── indexint1.php        # Main frontend dashboard

├── notifications.php    # Notification display + history page

├── setuser.php          # Simple login page

├── style.css            # Frontend styling

│

├── index.php            # Backend Notification API

│

├── schema.sql           # Database schema

├── .env                 # Environment variables

│

└── README.md
```
---

## System Workflow

### 1. User Login

Users log in through setuser.php.
A session is created and a Welcome notification is generated.

### 2. Notification Trigger

Notifications can be triggered by:

- Demo buttons in the frontend

- API requests from other services

### 3. Backend Processing

The backend:

Validates the request

Loads the notification template

Renders the message with variables

Saves the notification to the database

Attempts delivery through the preferred channel

### 4. Notification Display

Notifications appear as pop-up alerts on the dashboard.

### 5. Notification History

Notifications are stored in the database and can be viewed in the Notification History page.

---

## API Endpoints

Health Check:

GET

```
/index.php/health
```

Example:

```bash
https://vesta.uclan.ac.uk/~fatieh/jaffa/index.php/health
```

Example Response:

```json

{

  "status": "healthy",
  
  "timestamp": "2026-02-09T23:05:41+00:00",
  
  
  "service": "notification-service",
  
  "mailgun_configured": false

}
```

Send Notification:

POST:

```
/index.php/send
```

Example Request:

```bash

curl -X POST "https://vesta.uclan.ac.uk/~fatieh/jaffa/index.php/send" \

  -H "Content-Type: application/json" \
 
  -H "X-API-Key: YOUR_API_KEY" \
  
  -d '{
  
    "recipient_id": "11111111-1111-1111-1111-111111111111",
    
    "template_name": "welcome",
    
    "variable_data": {
    
      "name": "User",
      
      "product": "Health Matters"
    
    }
    
  }'
```

Example Response:

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

All API requests must include an API key.

Header:

```
X-API-Key: 7f3c9a4e8b1d2f6a0c5e3b9d7a2c1e8f4b6d0a3c9e1b
```

Requests without a valid API key will be rejected.

## Database Schema

Notifications Table:

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

---

## Recipient Preferences

```sql
CREATE TABLE recipient_preferences (

  recipient_id VARCHAR(255) PRIMARY KEY,
  
  email VARCHAR(255),
  
  preferred_channel VARCHAR(50) DEFAULT 'Email'

);
```

This table stores the user’s preferred notification delivery method.

---

## Templates

Notifications are generated using templates defined in the backend configuration.

Example template:

```php

'welcome' => 'Hi {{ name }}, welcome to {{ product }}!'
```


Variables are dynamically replaced when the notification is created.

Example output:

```
Hi John, welcome to Health Matters!
```

---

## Frontend Demonstration:

The frontend interface allows users to trigger example notifications.

Buttons simulate real system events such as:

- Appointment reminders

- Medication reminders

- Activity alerts

- Goal achievements

Notifications appear as animated popups and are saved to the notification history database.

---

## Rate Limiting

To protect the API from abuse, a rate limiter restricts requests.

Default:

60 requests per minute per client

If exceeded, the API returns:

429 Rate Limit Exceeded

## Email Delivery (Optional)

Email notifications can be sent using Mailgun.

Environment variables:

```
MAILGUN_API_KEY

MAILGUN_DOMAIN

MAILGUN_REGION
```

If Mailgun is not configured, notifications remain queued (demo mode).

---

## Technologies Used

- PHP

- MariaDB / MySQL

- REST API

- HTML

- CSS

- JavaScript

- Mailgun (optional)

## Deployment

The project is designed to run on a shared hosting environment such as the UCLan Vesta server.

Requirements:

- PHP 8+

- MariaDB / MySQL

- cURL enabled

- PDO enabled

---

## Educational Purpose

This system was developed as part of a university coursework project to demonstrate:

- API design

- Backend service architecture

- Database integration

- Frontend integration

- Notification system workflows
