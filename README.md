# Bank Notification Gateway Server

Central SaaS backend scaffold for receiving Android bank notification events, parsing transactions, deduplicating events, and forwarding signed webhooks to tenant customer systems.

## Stack

- Laravel-oriented PHP backend scaffold
- MySQL for MVP persistence
- Redis for queue/cache
- Queue workers for parsing and webhook delivery
- Docker Compose baseline for local deployment

## Core Security Rules

- Never trust tenant IDs from mobile clients.
- Resolve tenant from `device_id` only.
- Store only hashed device secrets.
- Sign incoming mobile requests with HMAC SHA256.
- Sign outgoing webhooks with tenant webhook secret.
- Scope every tenant-owned query by `tenant_id`.

## Production-Hardening Status

The server code has been upgraded from MVP scaffold toward production-oriented Laravel structure:

- One-time hashed pairing tokens in [`DevicePairingService`](app/Services/Mobile/DevicePairingService.php).
- Encrypted device secret storage using Laravel Crypt in [`DeviceAuthenticator`](app/Services/Mobile/DeviceAuthenticator.php).
- Production security tables in [`2026_06_08_000002_add_production_security_tables.php`](database/migrations/2026_06_08_000002_add_production_security_tables.php).
- Admin/tenant middleware placeholders in [`EnsureAdminUser`](app/Http/Middleware/EnsureAdminUser.php) and [`EnsureTenantScope`](app/Http/Middleware/EnsureTenantScope.php).
- Tenant policy scaffold in [`TenantPolicy`](app/Policies/TenantPolicy.php).
- Audit logger scaffold in [`AuditLogger`](app/Services/Audit/AuditLogger.php).
- Webhook retry scheduler command in [`RetryDueWebhooksCommand`](app/Console/Commands/RetryDueWebhooksCommand.php).
- Production readiness checklist config in [`production_readiness.php`](config/production_readiness.php).

Required production env values:

```text
APP_ENV=production
APP_DEBUG=false
DASHBOARD_FALLBACK_ENABLED=false
DEBUG_DEVICE_SECRET_HEADER_ENABLED=false
```

## Laravel Setup

Install dependencies:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Run local Laravel server:

```bash
php artisan serve --host=0.0.0.0 --port=8081
```

Open dashboard:

```text
http://localhost:8081
```

Run queue worker:

```bash
php artisan queue:work redis --tries=3 --timeout=120
```

## Local Docker Startup

```bash
cp .env.example .env
docker compose up -d --build
```

## Simple Server Dashboard UI

A lightweight static dashboard is available under [`public/index.html`](public/index.html). It is intentionally simple, clean, and fast so the server can be operated during MVP/POC before a full Filament dashboard is completed.

Dashboard files:

- [`public/index.html`](public/index.html)
- [`public/assets/dashboard.css`](public/assets/dashboard.css)
- [`public/assets/dashboard.js`](public/assets/dashboard.js)

Current UI sections:

- Overview metrics
- Tenants
- Android devices
- Notification events
- Webhook deliveries
- Quick pairing payload generator
- Real-device test checklist

Local access after Docker starts:

```text
http://localhost:8080
```

Static preview without Docker:

```bash
python3 -m http.server 8081 -d public
```

Then open:

```text
http://localhost:8081
```

Current UI data is loaded from Laravel API first, then falls back to mock data in [`public/assets/dashboard.js`](public/assets/dashboard.js) for local development only. In production, disable fallback behavior using `DASHBOARD_FALLBACK_ENABLED=false` and protect dashboard routes with admin auth middleware.

## Lightweight MVP API

The main runtime is now a Laravel 11 skeleton using [`public/index.php`](public/index.php), [`bootstrap/app.php`](bootstrap/app.php), [`routes/web.php`](routes/web.php), and [`routes/api.php`](routes/api.php).

The previous no-framework PHP router was preserved at [`public/mvp.php`](public/mvp.php). It uses JSON files in [`storage/data`](storage/data) for quick fallback testing before database-backed Laravel endpoints are fully populated. Do not expose [`public/mvp.php`](public/mvp.php) in production.

Available temporary endpoints:

```text
GET  /api/dashboard/summary
GET  /api/tenants
POST /api/tenants
POST /api/pairing-token
POST /api/webhooks/test
POST /api/v1/mobile/pair
GET  /api/v1/mobile/config
POST /api/v1/mobile/heartbeat
POST /api/v1/mobile/notifications
```

Seed data files:

- [`storage/data/tenants.json`](storage/data/tenants.json)
- [`storage/data/devices.json`](storage/data/devices.json)
- [`storage/data/events.json`](storage/data/events.json)
- [`storage/data/webhooks.json`](storage/data/webhooks.json)

Support classes:

- [`src/Support/JsonStore.php`](src/Support/JsonStore.php)
- [`src/Support/Response.php`](src/Support/Response.php)

Run the preserved no-framework MVP fallback without Laravel dependencies:

```bash
php -S localhost:8082 -t public public/mvp.php
```

Then open:

```text
http://localhost:8082
```

## API Protocol

### Pair Device

Endpoint:

```text
POST /api/v1/mobile/pair
```

Temporary MVP pairing token format:

```text
tenant:{tenant_id}
```

Example request:

```json
{
  "pairing_token": "tenant:1",
  "device_name": "Shop MB Phone 1",
  "app_version": "1.0.0",
  "android_version": "14"
}
```

Example response:

```json
{
  "device_id": "dev_xxx",
  "device_secret": "returned_once",
  "server_url": "http://localhost:8080"
}
```

Production note: replace this temporary token format with a real `device_pairing_tokens` table containing one-time hashed tokens, expiration time, consumed time, tenant ID, and creator ID.

### Signed Mobile Requests

Endpoints requiring mobile signing:

```text
GET /api/v1/mobile/config
POST /api/v1/mobile/heartbeat
POST /api/v1/mobile/notifications
```

Headers:

```text
X-Device-Id: dev_xxx
X-Timestamp: 1759980600
X-Signature: hmac_sha256(timestamp + "." + raw_body, device_secret)
```

Current scaffold also accepts this temporary debug header so the backend can verify the hashed device secret during early testing:

```text
X-Device-Secret-Debug: returned_once
```

Production note: remove the debug header and store device secrets in a reversible encrypted form or use a keyed verification design that does not require comparing against a hashed value before HMAC validation.

### Notification Ingest

Endpoint:

```text
POST /api/v1/mobile/notifications
```

Example body:

```json
{
  "package_name": "com.mbmobile",
  "app_name": "MB Bank",
  "title": "Biến động số dư",
  "text": "+2,500,000 VND. Nội dung: TOUR10082",
  "big_text": "TK 123456 nhận +2,500,000 VND. ND: TOUR10082",
  "posted_at": "2026-06-08T12:00:00+07:00",
  "notification_key": "com.mbmobile|1759980600000|abcxyz",
  "raw": {
    "category": "status",
    "priority": 1
  }
}
```

Processing steps:

1. Authenticate active device.
2. Resolve tenant from the device record.
3. Enforce allowed package whitelist.
4. Generate fallback event hash.
5. Deduplicate by notification key or event hash.
6. Store raw event.
7. Dispatch parser job.

### Heartbeat

Endpoint:

```text
POST /api/v1/mobile/heartbeat
```

Example body:

```json
{
  "battery_level": 86,
  "is_charging": true,
  "listener_enabled": true,
  "queue_pending": 0,
  "app_version": "1.0.0",
  "android_version": "14"
}
```

### Outgoing Webhook

Headers sent to customer endpoint:

```text
Content-Type: application/json
X-Event-Id: evt_xxx
X-Timestamp: 1759980603
X-Signature: hmac_sha256(timestamp + "." + raw_body, tenant_webhook_secret)
```

Payload type:

```text
bank.credit_alert
```

## Implemented Phase 1 Server Foundation

- Eloquent model relationships and casts.
- Core database migration for plans, tenants, devices, allowed packages, notification events, parsed transactions, tenant webhooks, and webhook deliveries.
- Pairing service scaffold.
- Device authentication service with replay timestamp check and HMAC validation.
- Notification ingest service with package whitelist enforcement and deduplication.
- Generic parser for amount, order code, and direction.
- Parser job that creates parsed transaction records and queues webhook deliveries.
- Webhook dispatch job with HMAC signing, HTTP send, retry metadata, and failure state updates.

## Next Production Tasks

1. Replace temporary pairing token logic with a persistent one-time token table.
2. Replace `X-Device-Secret-Debug` with a production-grade secret verification strategy.
3. Install a real Laravel application skeleton around this scaffold if not already generated.
4. Add Filament admin/customer dashboards.
5. Add policy and tenant-scoping tests.
6. Add scheduled retry command for failed webhook deliveries where `next_retry_at` is due.
7. Add seeders for plans, sample tenants, devices, packages, and webhooks.
