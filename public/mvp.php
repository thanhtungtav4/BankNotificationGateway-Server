<?php

declare(strict_types=1);

require __DIR__ . '/../src/Support/JsonStore.php';
require __DIR__ . '/../src/Support/Response.php';

use Server\Support\JsonStore;
use Server\Support\Response;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$store = new JsonStore(__DIR__ . '/../storage/data');

if ($path === '/' || $path === '/index.html') {
    readfile(__DIR__ . '/index.html');
    return;
}

if (! str_starts_with($path, '/api/')) {
    Response::json(['message' => 'Not found'], 404);
    return;
}

try {
    if ($method === 'GET' && $path === '/api/dashboard/summary') {
        $tenants = $store->read('tenants');
        $devices = $store->read('devices');
        $events = $store->read('events');
        $webhooks = $store->read('webhooks');

        Response::json([
            'metrics' => [
                ['label' => 'Tenants', 'value' => (string) count($tenants)],
                ['label' => 'Devices online', 'value' => count(array_filter($devices, fn ($d) => ($d['status'] ?? '') === 'online')) . ' / ' . count($devices)],
                ['label' => 'Notifications today', 'value' => (string) count($events)],
                ['label' => 'Webhook failed', 'value' => (string) count(array_filter($webhooks, fn ($w) => ($w['status'] ?? '') === 'failed'))],
            ],
            'tenants' => $tenants,
            'devices' => $devices,
            'events' => array_reverse($events),
            'webhooks' => array_reverse($webhooks),
        ]);
        return;
    }

    if ($method === 'GET' && $path === '/api/tenants') {
        Response::json(['data' => $store->read('tenants')]);
        return;
    }

    if ($method === 'POST' && $path === '/api/tenants') {
        $payload = Response::body();
        $tenant = [
            'id' => $store->nextId('tenants'),
            'name' => trim((string) ($payload['name'] ?? 'New Tenant')),
            'plan' => (string) ($payload['plan'] ?? 'Basic'),
            'status' => (string) ($payload['status'] ?? 'trial'),
            'devices' => 0,
            'webhooks' => 0,
        ];
        $store->append('tenants', $tenant);
        Response::json(['data' => $tenant], 201);
        return;
    }

    if ($method === 'POST' && $path === '/api/pairing-token') {
        $payload = Response::body();
        $tenantId = (int) ($payload['tenant_id'] ?? 1);
        $serverUrl = rtrim((string) ($payload['server_url'] ?? 'http://10.0.2.2:8080'), '/');
        Response::json([
            'server_url' => $serverUrl,
            'pairing_token' => 'tenant:' . $tenantId,
            'qr_payload' => json_encode([
                'server_url' => $serverUrl,
                'pairing_token' => 'tenant:' . $tenantId,
            ], JSON_UNESCAPED_SLASHES),
        ]);
        return;
    }

    if ($method === 'POST' && $path === '/api/v1/mobile/pair') {
        $payload = Response::body();
        $token = (string) ($payload['pairing_token'] ?? '');
        if (! str_starts_with($token, 'tenant:')) {
            Response::json(['message' => 'Invalid pairing token'], 422);
            return;
        }

        $tenantId = (int) substr($token, strlen('tenant:'));
        $tenant = findById($store->read('tenants'), $tenantId);
        if ($tenant === null) {
            Response::json(['message' => 'Tenant not found'], 404);
            return;
        }

        $deviceId = 'dev_' . strtolower(bin2hex(random_bytes(8)));
        $deviceSecret = bin2hex(random_bytes(24));
        $device = [
            'id' => $store->nextId('devices'),
            'tenant_id' => $tenantId,
            'tenant' => $tenant['name'],
            'device_id' => $deviceId,
            'device_secret' => $deviceSecret,
            'name' => (string) ($payload['device_name'] ?? 'Android Device'),
            'bank' => 'Not selected',
            'status' => 'online',
            'queue' => 0,
            'seen' => 'vừa xong',
            'app_version' => $payload['app_version'] ?? null,
            'android_version' => $payload['android_version'] ?? null,
            'allowed_packages' => [],
        ];
        $store->append('devices', $device);
        incrementTenantCounter($store, $tenantId, 'devices');

        Response::json([
            'device_id' => $deviceId,
            'device_secret' => $deviceSecret,
            'server_url' => requestBaseUrl(),
        ], 201);
        return;
    }

    if ($method === 'GET' && $path === '/api/v1/mobile/config') {
        $device = authenticateDevice($store);
        Response::json([
            'allowed_packages' => array_map(fn ($package) => [
                'package_name' => $package,
                'app_name' => $package,
                'bank_name' => $package,
            ], $device['allowed_packages'] ?? []),
            'heartbeat_interval_seconds' => 300,
        ]);
        return;
    }

    if ($method === 'POST' && $path === '/api/v1/mobile/heartbeat') {
        $device = authenticateDevice($store);
        $payload = Response::body();
        updateDevice($store, $device['device_id'], [
            'status' => 'online',
            'queue' => (int) ($payload['queue_pending'] ?? 0),
            'seen' => 'vừa xong',
            'battery_level' => $payload['battery_level'] ?? null,
            'listener_enabled' => (bool) ($payload['listener_enabled'] ?? false),
            'app_version' => $payload['app_version'] ?? ($device['app_version'] ?? null),
            'android_version' => $payload['android_version'] ?? ($device['android_version'] ?? null),
        ]);
        Response::json(['status' => 'ok']);
        return;
    }

    if ($method === 'POST' && $path === '/api/v1/mobile/notifications') {
        $device = authenticateDevice($store);
        $payload = Response::body();
        $content = trim((string) ($payload['title'] ?? '') . ' ' . (string) ($payload['text'] ?? '') . ' ' . (string) ($payload['big_text'] ?? ''));
        $parsed = parseNotification($content);
        $eventStatus = $parsed['amount'] === '-' || $parsed['order'] === '-' ? 'parse_failed' : 'parsed';
        $event = [
            'id' => $store->nextId('events'),
            'tenant_id' => $device['tenant_id'] ?? null,
            'tenant' => $device['tenant'] ?? null,
            'device_id' => $device['device_id'],
            'bank' => (string) ($payload['app_name'] ?? $payload['package_name'] ?? 'Unknown'),
            'package_name' => (string) ($payload['package_name'] ?? ''),
            'content' => $content !== '' ? $content : 'Notification content empty',
            'amount' => $parsed['amount'],
            'order' => $parsed['order'],
            'direction' => $parsed['direction'],
            'status' => $eventStatus,
            'raw' => $payload,
            'received_at' => date(DATE_ATOM),
        ];
        $store->append('events', $event);

        $delivery = [
            'id' => $store->nextId('webhooks'),
            'tenant' => $device['tenant'] ?? 'Unknown tenant',
            'url' => 'not_configured_yet',
            'status' => $eventStatus === 'parsed' ? 'pending' : 'failed',
            'attempt' => 0,
            'http' => '-',
            'event_id' => $event['id'],
        ];
        $store->append('webhooks', $delivery);

        updateDevice($store, $device['device_id'], [
            'bank' => (string) ($payload['app_name'] ?? $payload['package_name'] ?? 'Unknown'),
            'status' => 'online',
            'seen' => 'vừa xong',
        ]);

        Response::json(['status' => $eventStatus === 'parsed' ? 'accepted' : 'parse_failed', 'event_id' => $event['id']], 202);
        return;
    }

    if ($method === 'POST' && $path === '/api/webhooks/test') {
        $payload = Response::body();
        $delivery = [
            'id' => $store->nextId('webhooks'),
            'tenant' => (string) ($payload['tenant'] ?? 'Demo Shop'),
            'url' => (string) ($payload['url'] ?? 'https://example.com/webhook'),
            'status' => 'pending',
            'attempt' => 0,
            'http' => '-',
        ];
        $store->append('webhooks', $delivery);
        Response::json(['data' => $delivery], 202);
        return;
    }

    Response::json(['message' => 'Endpoint not found', 'path' => $path], 404);
} catch (Throwable $exception) {
    Response::json(['message' => 'Server error', 'error' => $exception->getMessage()], 500);
}

/** @param list<array<string,mixed>> $rows */
function findById(array $rows, int $id): ?array
{
    foreach ($rows as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            return $row;
        }
    }

    return null;
}

function authenticateDevice(JsonStore $store): array
{
    $deviceId = (string) ($_SERVER['HTTP_X_DEVICE_ID'] ?? '');
    $secret = (string) ($_SERVER['HTTP_X_DEVICE_SECRET_DEBUG'] ?? '');
    $devices = $store->read('devices');

    foreach ($devices as $device) {
        if (($device['device_id'] ?? '') === $deviceId && ($device['device_secret'] ?? '') === $secret) {
            return $device;
        }
    }

    Response::json(['message' => 'Invalid device credentials'], 401);
    exit;
}

/** @param array<string,mixed> $changes */
function updateDevice(JsonStore $store, string $deviceId, array $changes): void
{
    $devices = array_map(function (array $device) use ($deviceId, $changes): array {
        if (($device['device_id'] ?? '') === $deviceId) {
            return array_merge($device, $changes);
        }
        return $device;
    }, $store->read('devices'));
    $store->write('devices', $devices);
}

function incrementTenantCounter(JsonStore $store, int $tenantId, string $field): void
{
    $tenants = array_map(function (array $tenant) use ($tenantId, $field): array {
        if ((int) ($tenant['id'] ?? 0) === $tenantId) {
            $tenant[$field] = (int) ($tenant[$field] ?? 0) + 1;
        }
        return $tenant;
    }, $store->read('tenants'));
    $store->write('tenants', $tenants);
}

/** @return array{amount:string,order:string,direction:string} */
function parseNotification(string $content): array
{
    $amount = '-';
    if (preg_match('/([0-9]{1,3}(?:[,.][0-9]{3})+|[0-9]{5,})\s*(?:vnd|vnđ|đ)?/iu', $content, $matches)) {
        $amount = number_format((int) str_replace([',', '.'], '', $matches[1]));
    }

    $order = '-';
    if (preg_match('/(TOUR|DH|ORDER|BK|INV)[A-Z0-9-]{4,24}/i', $content, $matches)) {
        $order = strtoupper($matches[0]);
    }

    $direction = 'unknown';
    if (preg_match('/(\+|nhận|nhan|ghi có|ghi co|credit|tiền vào|tien vao)/iu', $content)) {
        $direction = 'in';
    }
    if (preg_match('/(trừ|tru|ghi nợ|ghi no|debit|chuyển đi|chuyen di|rút tiền|rut tien)/iu', $content)) {
        $direction = 'out';
    }

    return compact('amount', 'order', 'direction');
}

function requestBaseUrl(): string
{
    $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8081';
    return $scheme . '://' . $host;
}
