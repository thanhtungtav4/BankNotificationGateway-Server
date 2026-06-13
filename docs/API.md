# Bank Notification Gateway API Documentation

## Base URL

```
Production: https://notification.nttung.dev/api
Staging: https://staging.notification.nttung.dev/api
Local: http://localhost:8080/api
```

## Authentication

### Admin Authentication (Dashboard)

Dashboard API sử dụng Laravel Sanctum token authentication.

#### Login

```http
POST /admin/auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password123"
}
```

**Response (200):**
```json
{
  "message": "Đăng nhập thành công",
  "admin": {
    "id": 1,
    "name": "Admin",
    "email": "admin@example.com",
    "role": "admin"
  },
  "token": "1|abc123..."
}
```

#### Register (Super Admin only)

```http
POST /admin/auth/register
Content-Type: application/json

{
  "name": "New Admin",
  "email": "newadmin@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

#### Get Current User

```http
GET /admin/auth/me
Authorization: Bearer {token}
```

#### Logout

```http
POST /admin/auth/logout
Authorization: Bearer {token}
```

---

## Tenants

### List Tenants

```http
GET /tenants
Authorization: Bearer {token}
```

### Create Tenant

```http
POST /tenants
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Customer Shop",
  "status": "trial"
}
```

### Get Tenant

```http
GET /tenants/{id}
Authorization: Bearer {token}
```

### Update Tenant

```http
PATCH /tenants/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Updated Name",
  "status": "active"
}
```

### Delete Tenant

```http
DELETE /tenants/{id}
Authorization: Bearer {token}
```

### Get Tenant Quota

```http
GET /tenants/{id}/quota
Authorization: Bearer {token}
```

**Response:**
```json
{
  "data": {
    "plan": "Pro",
    "devices": {
      "limit": 5,
      "current": 2,
      "available": 3
    },
    "webhooks": {
      "limit": 5,
      "current": 1,
      "available": 4
    },
    "notifications": {
      "limit": 2000,
      "current_today": 150
    }
  }
}
```

---

## Devices

### List Devices

```http
GET /devices
Authorization: Bearer {token}
```

### Get Device

```http
GET /devices/{id}
Authorization: Bearer {token}
```

### Delete Device

```http
DELETE /devices/{id}
Authorization: Bearer {token}
```

---

## Webhooks

### List Webhooks

```http
GET /tenant-webhooks
Authorization: Bearer {token}
```

### Create Webhook

```http
POST /tenant-webhooks
Authorization: Bearer {token}
Content-Type: application/json

{
  "tenant_id": 1,
  "name": "My Webhook",
  "url": "https://example.com/webhook",
  "is_active": true,
  "event_types": ["notification.received", "notification.replay"]
}
```

### Update Webhook

```http
PATCH /tenant-webhooks/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "is_active": false
}
```

### Delete Webhook

```http
DELETE /tenant-webhooks/{id}
Authorization: Bearer {token}
```

---

## Pairing Token

### Create Pairing Token

```http
POST /pairing-token
Authorization: Bearer {token}
Content-Type: application/json

{
  "tenant_id": 1,
  "server_url": "https://notification.nttung.dev",
  "device_name": "Customer Phone"
}
```

**Response:**
```json
{
  "server_url": "https://notification.nttung.dev",
  "pairing_token": "pair_abc123...",
  "expires_in_seconds": 900,
  "qr_payload": "{\"server_url\":\"...\",\"pairing_token\":\"...\"}"
}
```

---

## Event Replay

### Replay Webhook for Event

```http
POST /v1/dashboard/events/{event_id}/replay
Authorization: Bearer {token}
```

---

## Audit Logs

### List Audit Logs

```http
GET /audit-logs
Authorization: Bearer {token}

Query Parameters:
- tenant_id: Filter by tenant
- action: Filter by action (created, updated, deleted)
- subject_type: Filter by subject type
- from_date: Start date (YYYY-MM-DD)
- to_date: End date (YYYY-MM-DD)
- per_page: Items per page (max 100)
```

### Get Audit Log Actions

```http
GET /audit-logs/actions
Authorization: Bearer {token}
```

---

## Tenant Users

### List Tenant Users

```http
GET /tenant-users
Authorization: Bearer {token}

Query: ?tenant_id=1
```

### Create Tenant User

```http
POST /tenant-users
Authorization: Bearer {token}
Content-Type: application/json

{
  "tenant_id": 1,
  "email": "user@example.com",
  "name": "User Name",
  "role": "viewer"
}
```

### Update Tenant User

```http
PATCH /tenant-users/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "role": "admin"
}
```

### Delete Tenant User

```http
DELETE /tenant-users/{id}
Authorization: Bearer {token}
```

---

## Mobile API

### Pair Device

```http
POST /v1/mobile/pair
Content-Type: application/json

{
  "pairing_token": "pair_abc123...",
  "device_name": "My Phone",
  "app_version": "1.0.0",
  "android_version": "14"
}
```

### Unpair Device

```http
POST /v1/mobile/unpair
Content-Type: application/json

{
  "device_id": "dev_abc123...",
  "device_secret": "secret123..."
}
```

### Get Device Config

```http
GET /v1/mobile/config
X-Device-Id: {device_id}
X-Timestamp: {timestamp}
X-Signature: {signature}
```

### Heartbeat

```http
POST /v1/mobile/heartbeat
X-Device-Id: {device_id}
X-Timestamp: {timestamp}
X-Signature: {signature}
Content-Type: application/json

{
  "battery_level": 85,
  "is_charging": false,
  "queue_pending": 2
}
```

### Send Notification

```http
POST /v1/mobile/notifications
X-Device-Id: {device_id}
X-Timestamp: {timestamp}
X-Signature: {signature}
Content-Type: application/json

{
  "package_name": "com.vietcombank",
  "app_name": "Vietcombank",
  "title": "Thông báo",
  "text": "TK 1234567890 +500,000VND",
  "posted_at": "2026-06-09T15:00:00Z",
  "notification_key": "vietcombank|123",
  "raw": { ... }
}
```

---

## Health Check

### Get Health Status

```http
GET /health
```

**Response:**
```json
{
  "status": "healthy",
  "timestamp": "2026-06-09T15:00:00Z",
  "version": "1.0.0",
  "components": {
    "database": {
      "status": "ok",
      "latency_ms": 5
    },
    "redis": {
      "status": "ok",
      "latency_ms": 2
    },
    "disk": {
      "status": "ok",
      "usage_percent": 29
    }
  }
}
```

---

## Error Responses

### Standard Error Format

```json
{
  "error": "error_code",
  "message": "Human readable message",
  "errors": { ... }  // Validation errors
}
```

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Rate Limit Exceeded |
| 500 | Server Error |

---

## Rate Limits

| Endpoint | Limit |
|----------|-------|
| Auth (login/register) | 5/minute |
| Dashboard API | 120/minute |
| Mobile API | 60/minute |

Response headers:
- `X-RateLimit-Limit`: Maximum requests
- `X-RateLimit-Remaining`: Remaining requests

---

## Webhook Payload Format

```json
{
  "event": "notification.received",
  "timestamp": "2026-06-09T15:00:00Z",
  "data": {
    "notification": {
      "id": 123,
      "package_name": "com.vietcombank",
      "app_name": "Vietcombank",
      "title": "Thông báo",
      "text": "TK 1234567890 +500,000VND",
      "posted_at": "2026-06-09T15:00:00Z"
    },
    "transaction": {
      "bank_name": "Vietcombank",
      "account_number": "1234567890",
      "amount": 500000,
      "currency": "VND",
      "direction": "credit",
      "order_code": "ABC123",
      "transfer_content": "Chuyen tien",
      "confidence": 0.95
    }
  }
}
```

Webhook Signature Verification:
```
signature = HMAC-SHA256(timestamp + "." + payload, device_secret)
```
