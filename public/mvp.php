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
        $tenantId = (int) ($device['tenant_id'] ?? 0);
        $tenantName = (string) ($device['tenant'] ?? 'Unknown tenant');
        $event = [
            'id' => $store->nextId('events'),
            'tenant_id' => $tenantId,
            'tenant' => $tenantName,
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

        $deliveries = fanOutWebhooks($store, $tenantId, $tenantName, $event, $eventStatus);

        updateDevice($store, $device['device_id'], [
            'bank' => (string) ($payload['app_name'] ?? $payload['package_name'] ?? 'Unknown'),
            'status' => 'online',
            'seen' => 'vừa xong',
        ]);

        Response::json([
            'status' => $eventStatus === 'parsed' ? 'accepted' : 'parse_failed',
            'event_id' => $event['id'],
            'webhook_dispatched' => count($deliveries),
        ], 202);
        return;
    }

    if ($method === 'GET' && $path === '/api/tenant-webhooks') {
        $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : 0;
        $rows = $store->read('tenant_webhooks');
        if ($tenantId > 0) {
            $rows = array_values(array_filter($rows, fn ($w) => (int) ($w['tenant_id'] ?? 0) === $tenantId));
        }
        Response::json(['data' => $rows]);
        return;
    }

    if ($method === 'POST' && $path === '/api/tenant-webhooks') {
        $payload = Response::body();
        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        $tenant = findById($store->read('tenants'), $tenantId);
        if ($tenant === null) {
            Response::json(['message' => 'Tenant not found'], 422);
            return;
        }
        $url = trim((string) ($payload['url'] ?? ''));
        if (! preg_match('#^https?://#i', $url)) {
            Response::json(['message' => 'Webhook URL phải bắt đầu bằng http(s)://'], 422);
            return;
        }
        $webhook = [
            'id' => $store->nextId('tenant_webhooks'),
            'tenant_id' => $tenantId,
            'name' => trim((string) ($payload['name'] ?? $tenant['name'] . ' webhook')),
            'url' => $url,
            'secret' => 'whk_' . bin2hex(random_bytes(8)),
            'event_types' => ['money_in'],
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ];
        $store->append('tenant_webhooks', $webhook);
        incrementTenantCounter($store, $tenantId, 'webhooks');
        Response::json(['data' => $webhook], 201);
        return;
    }

    if (preg_match('#^/api/tenant-webhooks/(\d+)$#', $path, $matches)) {
        $webhookId = (int) $matches[1];
        $rows = $store->read('tenant_webhooks');
        $index = null;
        foreach ($rows as $i => $row) {
            if ((int) ($row['id'] ?? 0) === $webhookId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            Response::json(['message' => 'Webhook not found'], 404);
            return;
        }

        if ($method === 'PATCH' || $method === 'PUT') {
            $payload = Response::body();
            $current = $rows[$index];
            if (isset($payload['url'])) {
                $url = trim((string) $payload['url']);
                if (! preg_match('#^https?://#i', $url)) {
                    Response::json(['message' => 'Webhook URL phải bắt đầu bằng http(s)://'], 422);
                    return;
                }
                $current['url'] = $url;
            }
            if (isset($payload['name'])) {
                $current['name'] = trim((string) $payload['name']);
            }
            if (isset($payload['is_active'])) {
                $current['is_active'] = (bool) $payload['is_active'];
            }
            if (isset($payload['event_types']) && is_array($payload['event_types'])) {
                $current['event_types'] = array_values(array_map('strval', $payload['event_types']));
            }
            $rows[$index] = $current;
            $store->write('tenant_webhooks', $rows);
            Response::json(['data' => $current]);
            return;
        }

        if ($method === 'DELETE') {
            $removed = $rows[$index];
            array_splice($rows, $index, 1);
            $store->write('tenant_webhooks', $rows);
            incrementTenantCounter($store, (int) ($removed['tenant_id'] ?? 0), 'webhooks', -1);
            Response::json(['data' => $removed]);
            return;
        }
    }

    if ($method === 'POST' && $path === '/api/webhooks/test') {
        $payload = Response::body();
        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        $tenant = findById($store->read('tenants'), $tenantId);
        if ($tenant === null) {
            Response::json(['message' => 'Chọn tenant hợp lệ trước khi test'], 422);
            return;
        }

        $stubEvent = [
            'id' => 0,
            'tenant_id' => $tenantId,
            'amount' => '500,000',
            'order' => 'TEST' . strtoupper(bin2hex(random_bytes(3))),
            'direction' => 'in',
            'bank' => 'Test Bank',
            'content' => 'Test webhook delivery from dashboard',
            'received_at' => date(DATE_ATOM),
        ];

        $deliveries = fanOutWebhooks($store, $tenantId, (string) $tenant['name'], $stubEvent, 'parsed', isTest: true);

        Response::json([
            'data' => $deliveries,
            'dispatched' => count($deliveries),
            'tenant' => $tenant['name'],
        ], 202);
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

function incrementTenantCounter(JsonStore $store, int $tenantId, string $field, int $delta = 1): void
{
    $tenants = array_map(function (array $tenant) use ($tenantId, $field, $delta): array {
        if ((int) ($tenant['id'] ?? 0) === $tenantId) {
            $tenant[$field] = max(0, (int) ($tenant[$field] ?? 0) + $delta);
        }
        return $tenant;
    }, $store->read('tenants'));
    $store->write('tenants', $tenants);
}

/**
 * Fan-out a parsed event to all active webhooks of a tenant.
 * Each active webhook gets its own WebhookDelivery row.
 *
 * @return list<array<string,mixed>> created deliveries
 */
function fanOutWebhooks(JsonStore $store, int $tenantId, string $tenantName, array $event, string $eventStatus, bool $isTest = false): array
{
    if ($tenantId <= 0) {
        return [];
    }

    $webhooks = array_values(array_filter(
        $store->read('tenant_webhooks'),
        fn ($w) => (int) ($w['tenant_id'] ?? 0) === $tenantId && ! empty($w['is_active'])
    ));

    if ($webhooks === []) {
        return [];
    }

    $created = [];
    foreach ($webhooks as $webhook) {
        $delivery = [
            'id' => $store->nextId('webhooks'),
            'tenant_id' => $tenantId,
            'webhook_id' => (int) ($webhook['id'] ?? 0),
            'tenant' => $tenantName,
            'webhook_name' => (string) ($webhook['name'] ?? ''),
            'url' => (string) ($webhook['url'] ?? ''),
            'status' => $eventStatus === 'parsed' ? 'pending' : 'failed',
            'attempt' => 0,
            'http' => '-',
            'event_id' => (int) ($event['id'] ?? 0),
            'is_test' => $isTest,
            'queued_at' => date(DATE_ATOM),
        ];
        $store->append('webhooks', $delivery);
        $created[] = $delivery;
    }

    return $created;
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
