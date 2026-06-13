const fallbackState = {
  metrics: [
    { label: 'Tenants', value: '—' },
    { label: 'Devices online', value: '—' },
    { label: 'Notifications today', value: '—' },
    { label: 'Webhook failed', value: '—' }
  ],
  tenants: [],
  devices: [],
  events: [],
  webhooks: []
};

let state = structuredClone(fallbackState);
let tenantsList = [];
let tenantWebhooks = [];
let liveMode = false;
let pollTimer = null;
let expiryTimer = null;
let toastTimer = null;

let selectedAmountText = '';
let selectedOrderText = '';
let selectedContentText = '';
let lastGeneratedRegex = null;

const titles = {
  overview: 'Tổng quan hệ thống',
  tenants: 'Quản lý khách hàng',
  devices: 'Thiết bị Android',
  events: 'Notification events',
  webhooks: 'Webhook delivery',
  setup: 'Pairing thiết bị',
  'tenant-detail': 'Chi tiết tenant',
  'ai-assistant': 'Trợ lý AI'
};

const okStatuses = new Set(['active', 'online', 'sent', 'parsed', 'forwarded']);
const deadStatuses = new Set(['failed', 'parse_failed', 'offline', 'suspended']);

function badge(status) {
  const cls = okStatuses.has(status) ? 'ok' : deadStatuses.has(status) ? 'dead' : 'warn';
  return `<span class="badge ${cls}">${status}</span>`;
}

function setStatus(mode, note) {
  liveMode = mode === 'live';
  const dot = document.querySelector('#live-dot');
  const statusEl = document.querySelector('#live-status');
  const noteEl = document.querySelector('#live-note');
  const pill = document.querySelector('#sync-pill');

  statusEl.textContent = liveMode ? 'Live API' : 'Fallback';
  noteEl.textContent = note;

  dot.classList.toggle('is-live', liveMode);
  dot.classList.toggle('is-fallback', !liveMode);

  pill.textContent = liveMode ? `Live · ${new Date().toLocaleTimeString('vi-VN')}` : 'Fallback mode';
  pill.classList.toggle('is-live', liveMode);
  pill.classList.toggle('is-fallback', !liveMode);
}

function render() {
  document.querySelector('#metric-grid').innerHTML = state.metrics.map(item => `
    <article class="metric"><span>${item.label}</span><strong>${item.value}</strong></article>
  `).join('');

  document.querySelector('#tenant-rows').innerHTML = state.tenants.length
    ? state.tenants.map(row => `
        <tr>
          <td><code>${row.id ?? '—'}</code></td>
          <td>${row.name}</td>
          <td>${row.plan ?? 'Manual'}</td>
          <td>${badge(row.status)}</td>
          <td>${row.devices ?? 0}</td>
          <td>${row.webhooks ?? 0}</td>
          <td style="white-space:nowrap">
            <button class="ghost-button" data-action="view-tenant" data-id="${row.id}">Xem</button>
            <button class="ghost-button" data-action="delete-tenant" data-id="${row.id}">Xoá</button>
          </td>
        </tr>`).join('')
    : `<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:24px">Chưa có tenant</td></tr>`;

  document.querySelector('#device-cards').innerHTML = state.devices.length
    ? state.devices.map(item => `
        <article class="device-card">
          <strong>${item.name}</strong>
          <p>${item.tenant} · ${item.bank}</p>
          <p>${badge(item.status)} · Queue: ${item.queue}</p>
          <p>Last seen: ${item.seen}</p>
          <button class="ghost-button" style="margin-top:8px" data-action="delete-device" data-id="${item.id}">Xoá thiết bị</button>
        </article>`).join('')
    : `<p style="color:var(--muted);text-align:center;padding:24px;margin:0">Chưa có thiết bị nào pair vào.</p>`;

  document.querySelector('#event-rows').innerHTML = state.events.length
    ? state.events.map(row => `
        <tr>
          <td>${row.bank}</td>
          <td>${row.content}</td>
          <td>${row.amount}</td>
          <td>${row.order}</td>
          <td>${badge(row.status)}</td>
        </tr>`).join('')
    : `<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:24px">Chưa có notification nào</td></tr>`;

  document.querySelector('#webhook-rows').innerHTML = state.webhooks.length
    ? state.webhooks.map(row => `
        <tr>
          <td>${row.tenant ?? '—'}</td>
          <td>${row.webhook_name ?? '—'}</td>
          <td><code class="mono">${row.url ?? '—'}</code></td>
          <td>${badge(row.status)}</td>
          <td>${row.http ?? '-'}</td>
        </tr>
      `).join('')
    : `<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:24px">Chưa có webhook delivery</td></tr>`;
}

function renderTenantWebhooks() {
  const count = document.querySelector('#webhook-count');
  const tbody = document.querySelector('#webhook-rows');
  if (!count || !tbody) return;

  count.textContent = `${tenantWebhooks.length} endpoint`;
  tbody.innerHTML = tenantWebhooks.length
    ? tenantWebhooks.map(row => {
        const active = row.is_active ? 'active' : 'paused';
        const tenantName = row.tenant ? row.tenant.name : `Tenant #${row.tenant_id}`;

        let regexText = '';
        let clearRegexBtn = '';
        if (row.bank_rules && row.bank_rules.regex) {
          regexText = `<div style="font-size: 11px; color: var(--accent); margin-top: 4px; font-family: monospace;">Regex: ${row.bank_rules.regex}</div>`;
          clearRegexBtn = `<button class="ghost-button" data-action="clear-regex" data-id="${row.id}" style="color: var(--accent); border-color: var(--accent);">Xoá Regex</button>`;
        }

        return `
          <tr data-webhook-id="${row.id}">
            <td>${tenantName}</td>
            <td>${row.name ?? '—'}${regexText}</td>
            <td><code class="mono">${row.url}</code></td>
            <td><span class="badge ${active}">${row.is_active ? 'active' : 'paused'}</span></td>
            <td style="white-space:nowrap">
              <button class="ghost-button" data-action="toggle" data-id="${row.id}">${row.is_active ? 'Pause' : 'Active'}</button>
              ${clearRegexBtn}
              <button class="ghost-button" data-action="delete" data-id="${row.id}">Xoá</button>
            </td>
          </tr>`;
      }).join('')
    : `<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:24px">Chưa có endpoint nào. Thêm bên trái.</td></tr>`;
}

function syncTenantSelects() {
  ['#webhook-tenant-select', '#webhook-test-tenant'].forEach(selector => {
    const el = document.querySelector(selector);
    if (!el) return;
    if (!tenantsList.length) {
      el.innerHTML = `<option value="">Chưa có tenant — tạo ở tab Khách hàng</option>`;
      el.disabled = true;
      return;
    }
    const prev = el.value;
    el.innerHTML = tenantsList
      .map(t => `<option value="${t.id}">#${t.id} · ${t.name}</option>`)
      .join('');
    el.disabled = false;
    if (prev && tenantsList.some(t => String(t.id) === prev)) el.value = prev;
  });
}

function syncWebhookSelects() {
  const el = document.querySelector('#ai-regex-webhook-select');
  if (!el) return;
  if (!tenantWebhooks.length) {
    el.innerHTML = `<option value="">Chưa có webhook — tạo ở tab Webhook</option>`;
    el.disabled = true;
    return;
  }
  const prev = el.value;
  el.innerHTML = tenantWebhooks
    .map(w => `<option value="${w.id}">${w.name} (${w.url})</option>`)
    .join('');
  el.disabled = false;
  if (prev && tenantWebhooks.some(w => String(w.id) === prev)) {
    el.value = prev;
  }
}

function switchAppView(viewName) {
  const landing = document.querySelector('#landing-container');
  const auth = document.querySelector('#auth-container');
  const shell = document.querySelector('.shell');

  if (viewName === 'landing') {
    if (landing) landing.style.display = 'block';
    if (auth) auth.style.display = 'none';
    if (shell) shell.style.display = 'none';
  } else if (viewName === 'auth') {
    if (landing) landing.style.display = 'none';
    if (auth) {
      auth.style.display = 'grid';
      document.querySelector('#login-view').hidden = false;
      document.querySelector('#register-view').hidden = true;
    }
    if (shell) shell.style.display = 'none';
  } else if (viewName === 'dashboard') {
    if (landing) landing.style.display = 'none';
    if (auth) auth.style.display = 'none';
    if (shell) shell.style.display = '';
  }
}

function showAuthView(show) {
  if (show) {
    switchAppView('auth');
  } else {
    switchAppView('dashboard');
  }
}

async function api(path, options = {}) {
  const token = localStorage.getItem('admin_token');
  const { headers: customHeaders, ...restOptions } = options;
  const response = await fetch(path, {
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(customHeaders ?? {})
    },
    ...restOptions
  });
  if (response.status === 401 && !path.includes('auth/login') && !path.includes('auth/register')) {
    localStorage.removeItem('admin_token');
    localStorage.removeItem('admin_user_type');
    window.history.pushState({}, '', '/login');
    showAuthView(true);
    throw new Error('HTTP 401');
  }
  if (!response.ok) throw new Error(`HTTP ${response.status}`);
  return response.json();
}

async function loadDashboard() {
  try {
    state = await api('/api/dashboard/summary');
    showAuthView(false);
    setStatus('live', 'Đang lấy dữ liệu thật. Auto-refresh 5 giây.');
  } catch (error) {
    state = structuredClone(fallbackState);
    if (error.message === 'HTTP 401') {
      setStatus('fallback', 'Vui lòng đăng nhập.');
    } else {
      setStatus('fallback', `API chưa sẵn sàng: ${error.message}.`);
    }
  }
  render();
}

async function loadTenants() {
  const select = document.querySelector('#pair-tenant');
  try {
    const resp = await api('/api/tenants');
    tenantsList = Array.isArray(resp) ? resp : (resp.data ?? []);
  } catch (_) {
    tenantsList = [];
  }
  syncTenantSelects();
  if (!tenantsList.length) {
    select.innerHTML = `<option value="">Chưa có tenant — tạo ở tab Khách hàng</option>`;
    select.disabled = true;
    document.querySelector('#make-token').disabled = true;
    return;
  }
  select.innerHTML = tenantsList
    .map(t => `<option value="${t.id}">#${t.id} · ${t.name}${t.status ? ` (${t.status})` : ''}</option>`)
    .join('');
  select.disabled = false;
  document.querySelector('#make-token').disabled = false;
}

async function loadTenantWebhooks() {
  try {
    const resp = await api('/api/tenant-webhooks');
    tenantWebhooks = resp.data ?? [];
  } catch (_) {
    tenantWebhooks = [];
  }
  renderTenantWebhooks();
  syncWebhookSelects();
}

function showView(view) {
  const userType = localStorage.getItem('admin_user_type');
  if (userType === 'tenant' && view === 'tenants') {
    view = 'overview';
  }
  document.querySelectorAll('.view').forEach(el => el.classList.toggle('is-visible', el.id === view));
  document.querySelectorAll('.nav-item').forEach(el => el.classList.toggle('is-active', el.dataset.view === view));
  document.querySelector('#view-title').textContent = titles[view] || titles.overview;
}

function renderQR(text) {
  const target = document.querySelector('#qr-canvas');
  target.innerHTML = '';
  if (typeof qrcode !== 'function') {
    target.textContent = 'QR lib failed to load';
    return;
  }
  const qr = qrcode(0, 'M');
  qr.addData(text);
  qr.make();
  target.innerHTML = qr.createSvgTag({ scalable: true, margin: 0 });
}

function formatRemaining(seconds) {
  if (seconds <= 0) return 'Đã hết hạn';
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `Hết hạn sau ${m}:${String(s).padStart(2, '0')}`;
}

function startExpiryCountdown(seconds) {
  clearInterval(expiryTimer);
  let remaining = seconds;
  const el = document.querySelector('#pairing-expiry');
  el.textContent = formatRemaining(remaining);
  expiryTimer = setInterval(() => {
    remaining -= 1;
    el.textContent = formatRemaining(remaining);
    if (remaining <= 0) {
      clearInterval(expiryTimer);
      el.style.color = 'var(--danger)';
    } else {
      el.style.color = '';
    }
  }, 1000);
}

async function createPairingPayload() {
  const tenantId = document.querySelector('#pair-tenant').value;
  const serverUrl = document.querySelector('#server-url').value.trim() || window.location.origin;
  const deviceName = document.querySelector('#device-name').value.trim();

  if (!tenantId) {
    toast('Chọn tenant trước', 'warn');
    return;
  }

  const button = document.querySelector('#make-token');
  button.disabled = true;
  const original = button.innerHTML;
  button.innerHTML = 'Đang tạo…';

  try {
    const payload = await api('/api/pairing-token', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        tenant_id: Number(tenantId),
        server_url: serverUrl,
        ...(deviceName ? { device_name: deviceName } : {})
      })
    });

    const qrText = payload.qr_payload ?? JSON.stringify({
      server_url: payload.server_url,
      pairing_token: payload.pairing_token
    });

    document.querySelector('#pair-server').textContent = payload.server_url;
    document.querySelector('#pair-token').textContent = payload.pairing_token;
    document.querySelector('#pair-json').textContent = JSON.stringify(payload, null, 2);
    renderQR(qrText);

    document.querySelector('#pairing-result').dataset.state = 'ready';
    document.querySelector('#pairing-content').hidden = false;

    startExpiryCountdown(payload.expires_in_seconds ?? 900);
    toast('Đã tạo pairing token', 'ok');
  } catch (error) {
    toast(`Tạo token thất bại: ${error.message}`, 'dead');
  } finally {
    button.disabled = false;
    button.innerHTML = original;
  }
}

async function createTenant(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = Object.fromEntries(new FormData(form).entries());
  document.querySelector('#tenant-form-status').textContent = 'Đang lưu…';
  try {
    await api('/api/tenants', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    form.reset();
    document.querySelector('#tenant-form-status').textContent = 'Đã tạo';
    toast('Tạo tenant thành công', 'ok');
    await Promise.all([loadDashboard(), loadTenants()]);
  } catch (error) {
    document.querySelector('#tenant-form-status').textContent = `Lỗi: ${error.message}`;
    toast(`Tạo tenant thất bại: ${error.message}`, 'dead');
  }
}

async function testWebhook(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = Object.fromEntries(new FormData(form).entries());
  const tenantId = Number(data.tenant_id);
  if (!tenantId) {
    toast('Chọn tenant trước', 'warn');
    return;
  }
  document.querySelector('#webhook-form-status').textContent = 'Đang queue…';
  try {
    const resp = await api('/api/webhooks/test', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ tenant_id: tenantId })
    });
    const count = resp.dispatched ?? 0;
    document.querySelector('#webhook-form-status').textContent = `Đã queue ${count} endpoint`;
    toast(`Đã queue ${count} webhook cho ${resp.tenant}`, count > 0 ? 'ok' : 'warn');
    await loadDashboard();
  } catch (error) {
    document.querySelector('#webhook-form-status').textContent = `Lỗi: ${error.message}`;
    toast(`Test webhook thất bại: ${error.message}`, 'dead');
  }
}

async function createTenantWebhook(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = Object.fromEntries(new FormData(form).entries());
  const tenantId = Number(data.tenant_id);
  if (!tenantId) {
    toast('Chọn tenant trước', 'warn');
    return;
  }
  document.querySelector('#webhook-endpoint-status').textContent = 'Đang lưu…';
  try {
    await api('/api/tenant-webhooks', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ tenant_id: tenantId, name: data.name, url: data.url })
    });
    form.reset();
    document.querySelector('#webhook-endpoint-status').textContent = 'Đã thêm';
    toast('Đã thêm webhook endpoint', 'ok');
    await Promise.all([loadDashboard(), loadTenantWebhooks(), loadTenants()]);
  } catch (error) {
    document.querySelector('#webhook-endpoint-status').textContent = `Lỗi: ${error.message}`;
    toast(`Thêm webhook thất bại: ${error.message}`, 'dead');
  }
}

async function toggleTenantWebhook(id) {
  const webhook = tenantWebhooks.find(w => Number(w.id) === Number(id));
  if (!webhook) return;
  try {
    await api(`/api/tenant-webhooks/${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ is_active: !webhook.is_active })
    });
    await loadTenantWebhooks();
    toast(webhook.is_active ? 'Đã pause webhook' : 'Đã active webhook', 'ok');
  } catch (error) {
    toast(`Thao tác thất bại: ${error.message}`, 'dead');
  }
}

async function deleteTenantWebhook(id) {
  if (!confirm('Xoá webhook này? Hành động không hoàn tác.')) return;
  try {
    await api(`/api/tenant-webhooks/${id}`, { method: 'DELETE' });
    await Promise.all([loadTenantWebhooks(), loadDashboard(), loadTenants()]);
    toast('Đã xoá webhook', 'ok');
  } catch (error) {
    toast(`Xoá thất bại: ${error.message}`, 'dead');
  }
}

async function clearWebhookRegex(id) {
  if (!confirm('Bạn có chắc chắn muốn xoá Regex tùy chỉnh cho Webhook này? Cấu hình sẽ quay lại mặc định của hệ thống.')) {
    return;
  }
  try {
    await api(`/api/tenant-webhooks/${id}/parser-config`, {
      method: 'DELETE'
    });
    toast('Đã xoá cấu hình Regex thành công!', 'ok');
    await loadTenantWebhooks();
  } catch (error) {
    toast(`Lỗi khi xoá Regex: ${error.message}`, 'dead');
  }
}

async function deleteTenant(id) {
  if (!confirm('Xoá tenant này? Tất cả thiết bị, webhook, notification sẽ bị xoá. Hành động không hoàn tác.')) return;
  try {
    await api(`/api/tenants/${id}`, { method: 'DELETE' });
    toast('Đã xoá tenant', 'ok');
    await Promise.all([loadDashboard(), loadTenants()]);
  } catch (error) {
    toast(`Xoá thất bại: ${error.message}`, 'dead');
  }
}

async function deleteDevice(id) {
  if (!confirm('Xoá thiết bị này?')) return;
  try {
    await api(`/api/devices/${id}`, { method: 'DELETE' });
    toast('Đã xoá thiết bị', 'ok');
    await loadDashboard();
  } catch (error) {
    toast(`Xoá thất bại: ${error.message}`, 'dead');
  }
}

async function viewTenantDetail(id) {
  try {
    const resp = await api(`/api/tenants/${id}`);
    const tenant = resp.data ?? resp;
    document.querySelector('#tenant-detail-title').textContent = `Chi tiết: ${tenant.name}`;
    document.querySelector('#tenant-detail-content').innerHTML = `
      <div class="info-grid">
        <div><strong>ID:</strong> ${tenant.id}</div>
        <div><strong>Slug:</strong> ${tenant.slug}</div>
        <div><strong>Status:</strong> ${badge(tenant.status)}</div>
        <div><strong>Plan:</strong> ${tenant.plan ?? 'Manual'}</div>
      </div>
      <h3 style="margin-top:16px">Cấu hình bóc tách (Parser Config)</h3>
      ${(tenant.parser_configs || []).length ? tenant.parser_configs.map(pc => `
        <div style="background: var(--surface); border: 1px solid var(--line); padding: 12px; border-radius: var(--radius-md); margin-top: 8px;">
          <div style="margin-bottom: 6px;"><strong>Trạng thái:</strong> ${pc.is_active ? '<span class="badge ok">Đang hoạt động</span>' : '<span class="badge warn">Tạm ngưng</span>'}</div>
          ${pc.bank_rules && pc.bank_rules.regex ? `
            <div style="margin-top: 6px; word-break: break-all;"><strong>Regex:</strong> <code class="mono" style="color: var(--primary); font-size: 13px;">${pc.bank_rules.regex}</code></div>
            <div style="margin-top: 6px; font-size: 12px; color: var(--text-muted); display: flex; gap: 12px; flex-wrap: wrap;">
              <span>Nhóm Số tiền: <strong>${pc.bank_rules.amount_group !== null ? pc.bank_rules.amount_group : 'null'}</strong></span>
              <span>Nhóm Mã đơn: <strong>${pc.bank_rules.order_code_group !== null ? pc.bank_rules.order_code_group : 'null'}</strong></span>
              <span>Nhóm Nội dung: <strong>${pc.bank_rules.transfer_content_group !== null ? pc.bank_rules.transfer_content_group : 'null'}</strong></span>
            </div>
          ` : `
            <div style="margin-top: 6px; color: var(--text-muted); font-size: 13px;">Đang sử dụng regex mặc định hệ thống.</div>
          `}
        </div>
      `).join('') : '<p style="color: var(--text-muted); font-size: 13px; margin-top: 8px;">Đang sử dụng cấu hình mặc định của hệ thống.</p>'}
      <h3 style="margin-top:16px">Thiết bị (${(tenant.devices || []).length})</h3>
      ${(tenant.devices || []).length ? tenant.devices.map(d => `
        <div class="card-grid" style="margin-top:8px">
          <article class="device-card">
            <strong>${d.device_name}</strong>
            <p>Device ID: ${d.device_id}</p>
            <p>${badge(d.status)} · Last seen: ${d.last_seen_at || 'never'}</p>
          </article>
        </div>
      `).join('') : '<p>Chưa có thiết bị</p>'}
    `;
    showView('tenant-detail');
  } catch (error) {
    toast(`Lỗi: ${error.message}`, 'dead');
  }
}

async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch (_) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); return true; }
    catch (e) { return false; }
    finally { ta.remove(); }
  }
}

function toast(message, kind = 'ok') {
  const el = document.querySelector('#toast');
  el.textContent = message;
  el.classList.add('is-visible');
  el.style.background = kind === 'dead' ? 'var(--danger)' : kind === 'warn' ? 'var(--warn)' : 'var(--ink)';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('is-visible'), 2200);
}

function startPolling() {
  clearInterval(pollTimer);
  pollTimer = setInterval(loadDashboard, 5000);
}

async function handleAIRegexSubmit(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const submitBtn = document.querySelector('#ai-regex-submit');
  const resultDiv = document.querySelector('#ai-regex-result');
  const statusEl = document.querySelector('#ai-regex-status');
  
  const data = Object.fromEntries(new FormData(form).entries());
  
  submitBtn.disabled = true;
  const originalHtml = submitBtn.innerHTML;
  submitBtn.innerHTML = 'Đang xử lý bằng AI...';
  statusEl.textContent = 'AI đang phân tích & sinh Regex, vui lòng chờ...';
  resultDiv.style.display = 'none';

  try {
    const res = await api('/api/dashboard/ai/generate-regex', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        sample_text: data.sample_text,
        bank_name: data.bank_name || null,
        provider: data.provider || null,
        selected_amount: selectedAmountText || null,
        selected_order: selectedOrderText || null,
        selected_content: selectedContentText || null
      })
    });

    document.querySelector('#ai-regex-pattern').textContent = res.regex || '—';
    document.querySelector('#ai-group-amount').textContent = res.amount_group !== null ? res.amount_group : 'null';
    document.querySelector('#ai-group-direction').textContent = res.direction_group !== null ? res.direction_group : 'null';
    document.querySelector('#ai-group-order').textContent = res.order_code_group !== null ? res.order_code_group : 'null';
    document.querySelector('#ai-group-content').textContent = res.transfer_content_group !== null ? res.transfer_content_group : 'null';
    document.querySelector('#ai-regex-explanation').textContent = res.explanation || '—';
    document.querySelector('#ai-regex-model').textContent = res.model_used || '—';
    document.querySelector('#ai-regex-provider').textContent = res.provider_used || '—';

    lastGeneratedRegex = {
      regex: res.regex,
      amount_group: res.amount_group,
      direction_group: res.direction_group,
      order_code_group: res.order_code_group,
      transfer_content_group: res.transfer_content_group
    };

    resultDiv.style.display = 'block';
    statusEl.textContent = 'Hoàn thành sinh Regex';
    toast('Đã sinh Regex thành công!', 'ok');
  } catch (error) {
    statusEl.textContent = `Lỗi: ${error.message}`;
    toast(`Lỗi AI: ${error.message}`, 'dead');
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalHtml;
  }
}

async function handleAIParseSubmit(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const submitBtn = document.querySelector('#ai-parse-submit');
  const resultDiv = document.querySelector('#ai-parse-result');
  const statusEl = document.querySelector('#ai-parse-status');
  
  const data = Object.fromEntries(new FormData(form).entries());
  
  submitBtn.disabled = true;
  const originalHtml = submitBtn.innerHTML;
  submitBtn.innerHTML = 'Đang phân tích bằng AI...';
  statusEl.textContent = 'AI đang bóc tách giao dịch mẫu, vui lòng chờ...';
  resultDiv.style.display = 'none';

  try {
    const res = await api('/api/dashboard/ai/parse', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        sample_text: data.sample_text,
        provider: data.provider || null
      })
    });

    const amountStr = res.amount !== null ? Number(res.amount).toLocaleString('vi-VN') + ' VND' : '—';
    document.querySelector('#ai-parse-amount').textContent = amountStr;
    document.querySelector('#ai-parse-bank').textContent = res.bank_name || '—';
    document.querySelector('#ai-parse-order').textContent = res.order_code || '—';
    document.querySelector('#ai-parse-content').textContent = res.transfer_content || '—';
    document.querySelector('#ai-parse-confidence').textContent = res.confidence !== undefined ? `${Math.round(res.confidence * 100)}%` : '—';
    document.querySelector('#ai-parse-model').textContent = res.model_used || '—';
    document.querySelector('#ai-parse-provider').textContent = res.provider_used || '—';

    const dirBadge = document.querySelector('#ai-parse-direction-badge');
    dirBadge.textContent = res.direction || '—';
    dirBadge.className = 'badge';
    if (res.direction === 'in') dirBadge.classList.add('ok');
    else if (res.direction === 'out') dirBadge.classList.add('dead');
    else dirBadge.classList.add('warn');

    resultDiv.style.display = 'block';
    statusEl.textContent = 'Hoàn thành bóc tách thử';
    toast('Đã phân tích giao dịch thành công!', 'ok');
  } catch (error) {
    statusEl.textContent = `Lỗi: ${error.message}`;
    toast(`Lỗi AI: ${error.message}`, 'dead');
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalHtml;
  }
}

// ===== Init =====

document.querySelectorAll('[data-view]').forEach(button => button.addEventListener('click', () => showView(button.dataset.view)));
document.querySelectorAll('[data-view-jump]').forEach(button => button.addEventListener('click', () => showView(button.dataset.viewJump)));
document.querySelector('#refresh-button').addEventListener('click', loadDashboard);
document.querySelector('#make-token').addEventListener('click', createPairingPayload);
document.querySelector('#tenant-form').addEventListener('submit', createTenant);
document.querySelector('#webhook-form').addEventListener('submit', testWebhook);
document.querySelector('#webhook-endpoint-form')?.addEventListener('submit', createTenantWebhook);
document.querySelector('#ai-regex-form').addEventListener('submit', handleAIRegexSubmit);
document.querySelector('#ai-parse-form').addEventListener('submit', handleAIParseSubmit);

// Text Highlighter functionality
document.querySelector('#btn-select-amount')?.addEventListener('click', () => {
  const textarea = document.querySelector('#ai-regex-sample');
  const selected = textarea.value.substring(textarea.selectionStart, textarea.selectionEnd).trim();
  if (selected) {
    selectedAmountText = selected;
    document.querySelector('#selected-amount').textContent = selected;
    document.querySelector('#selection-amount-row').style.display = 'block';
  } else {
    toast('Hãy bôi đen phần chữ số tiền trong khung tin nhắn trước', 'warn');
  }
});

document.querySelector('#btn-select-order')?.addEventListener('click', () => {
  const textarea = document.querySelector('#ai-regex-sample');
  const selected = textarea.value.substring(textarea.selectionStart, textarea.selectionEnd).trim();
  if (selected) {
    selectedOrderText = selected;
    document.querySelector('#selected-order').textContent = selected;
    document.querySelector('#selection-order-row').style.display = 'block';
  } else {
    toast('Hãy bôi đen phần chữ mã đơn trong khung tin nhắn trước', 'warn');
  }
});

document.querySelector('#btn-select-content')?.addEventListener('click', () => {
  const textarea = document.querySelector('#ai-regex-sample');
  const selected = textarea.value.substring(textarea.selectionStart, textarea.selectionEnd).trim();
  if (selected) {
    selectedContentText = selected;
    document.querySelector('#selected-content').textContent = selected;
    document.querySelector('#selection-content-row').style.display = 'block';
  } else {
    toast('Hãy bôi đen phần chữ nội dung trong khung tin nhắn trước', 'warn');
  }
});

document.querySelector('#clear-selected-amount')?.addEventListener('click', () => {
  selectedAmountText = '';
  document.querySelector('#selection-amount-row').style.display = 'none';
});

document.querySelector('#clear-selected-order')?.addEventListener('click', () => {
  selectedOrderText = '';
  document.querySelector('#selection-order-row').style.display = 'none';
});

document.querySelector('#clear-selected-content')?.addEventListener('click', () => {
  selectedContentText = '';
  document.querySelector('#selection-content-row').style.display = 'none';
});

document.querySelector('#btn-save-regex-config')?.addEventListener('click', async () => {
  const webhookId = document.querySelector('#ai-regex-webhook-select').value;
  if (!webhookId) {
    toast('Vui lòng chọn một Webhook', 'warn');
    return;
  }
  if (!lastGeneratedRegex) {
    toast('Chưa có cấu hình Regex nào được sinh ra', 'warn');
    return;
  }

  const btn = document.querySelector('#btn-save-regex-config');
  btn.disabled = true;
  const originalText = btn.textContent;
  btn.textContent = 'Đang lưu...';

  try {
    await api(`/api/tenant-webhooks/${webhookId}/parser-config`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(lastGeneratedRegex)
    });
    toast('Đã lưu cấu hình Regex thành công!', 'ok');
    await loadTenantWebhooks();
  } catch (error) {
    toast(`Lỗi khi lưu: ${error.message}`, 'dead');
  } finally {
    btn.disabled = false;
    btn.textContent = originalText;
  }
});

document.querySelector('#webhook-endpoint-rows')?.addEventListener('click', event => {
  const btn = event.target.closest('button[data-action]');
  if (!btn) return;
  const id = Number(btn.dataset.id);
  if (btn.dataset.action === 'toggle') toggleTenantWebhook(id);
  if (btn.dataset.action === 'delete') deleteTenantWebhook(id);
  if (btn.dataset.action === 'clear-regex') clearWebhookRegex(id);
});

document.querySelector('#tenant-rows')?.addEventListener('click', event => {
  const btn = event.target.closest('button[data-action]');
  if (!btn) return;
  const id = Number(btn.dataset.id);
  if (btn.dataset.action === 'delete-tenant') deleteTenant(id);
  if (btn.dataset.action === 'view-tenant') viewTenantDetail(id);
});

document.querySelector('#device-cards')?.addEventListener('click', event => {
  const btn = event.target.closest('button[data-action]');
  if (!btn) return;
  if (btn.dataset.action === 'delete-device') deleteDevice(Number(btn.dataset.id));
});

document.addEventListener('click', async event => {
  const copyBtn = event.target.closest('[data-copy-target]');
  if (!copyBtn) return;
  const target = document.getElementById(copyBtn.dataset.copyTarget);
  if (!target) return;
  const ok = await copyText(target.textContent.trim());
  toast(ok ? 'Đã copy' : 'Copy thất bại', ok ? 'ok' : 'dead');
});

// Auth UI toggles
document.querySelector('#show-register').addEventListener('click', (e) => {
  e.preventDefault();
  document.querySelector('#login-view').hidden = true;
  document.querySelector('#register-view').hidden = false;
});

document.querySelector('#show-login').addEventListener('click', (e) => {
  e.preventDefault();
  document.querySelector('#login-view').hidden = false;
  document.querySelector('#register-view').hidden = true;
});

// Login Form
document.querySelector('#login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.currentTarget;
  const data = Object.fromEntries(new FormData(form).entries());
  const loginType = data.login_type || 'admin';
  const url = loginType === 'admin' ? '/api/admin/auth/login' : '/api/tenant/auth/login';
  try {
    const res = await api(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: data.email, password: data.password })
    });
    if (res.token) {
      localStorage.setItem('admin_token', res.token);
      localStorage.setItem('admin_user_type', loginType);
      toast('Đăng nhập thành công', 'ok');
      form.reset();
      
      // Adapt UI based on role
      document.querySelector('.nav-item[data-view="tenants"]').style.display = loginType === 'tenant' ? 'none' : '';
      
      showAuthView(false);
      if (window.location.pathname === '/login' || window.location.pathname === '/') {
        window.history.pushState({}, '', '/admin');
      }
      await Promise.all([loadDashboard(), loadTenants()]);
      startPolling();
    }
  } catch (err) {
    toast('Đăng nhập thất bại: ' + err.message, 'dead');
  }
});

// Register Form
document.querySelector('#register-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.currentTarget;
  const data = Object.fromEntries(new FormData(form).entries());
  if (data.password !== data.password_confirmation) {
    toast('Mật khẩu xác nhận không khớp', 'dead');
    return;
  }
  try {
    await api('/api/admin/auth/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    toast('Đăng ký thành công! Hãy đăng nhập.', 'ok');
    form.reset();
    document.querySelector('#login-view').hidden = false;
    document.querySelector('#register-view').hidden = true;
  } catch (err) {
    toast('Đăng ký thất bại: ' + err.message, 'dead');
  }
});

// Logout Button
document.querySelector('#logout-button').addEventListener('click', async () => {
  const loginType = localStorage.getItem('admin_user_type') || 'admin';
  const url = loginType === 'admin' ? '/api/admin/auth/logout' : '/api/tenant/auth/logout';
  try {
    await api(url, { method: 'POST' });
  } catch (_) {}
  localStorage.removeItem('admin_token');
  localStorage.removeItem('admin_user_type');
  toast('Đã đăng xuất', 'ok');
  window.history.pushState({}, '', '/login');
  showAuthView(true);
});

document.querySelector('#server-url').value = window.location.origin;

document.querySelector('#login-type').addEventListener('change', (e) => {
  const isTenant = e.target.value === 'tenant';
  document.querySelector('#register-toggle-p').style.display = isTenant ? 'none' : '';
});

// Landing page navigation helper
function navigateToLogin() {
  window.history.pushState({}, '', '/login');
  switchAppView('auth');
}

// Interactive Demo Simulation
async function triggerDemoTransfer() {
  const bank = document.querySelector('#demo-bank').value;
  const amount = document.querySelector('#demo-amount').value || '200000';
  const content = document.querySelector('#demo-content').value || 'NAP TIEN ACC123';
  const btn = document.querySelector('#demo-trigger-btn');

  if (btn.disabled) return;
  btn.disabled = true;
  const origText = btn.textContent;
  btn.textContent = 'Đang chuyển khoản...';

  // Step 1: Simulate bank push alert
  await new Promise(r => setTimeout(r, 850));
  
  document.querySelector('#notif-bank-name').textContent = bank;
  document.querySelector('#notif-title').textContent = 'Biến động số dư';
  
  const formattedAmount = Number(amount).toLocaleString('vi-VN') + 'đ';
  const now = new Date();
  const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
  
  document.querySelector('#notif-text').textContent = `GD: +${formattedAmount} luc ${timeStr}. ND: ${content}`;
  
  const notif = document.querySelector('#phone-notif');
  notif.style.display = 'block';
  document.querySelector('#phone-app-status').textContent = 'Đã nhận biến động số dư 🔎';

  // Step 2: Convert to Webhook and fan-out
  await new Promise(r => setTimeout(r, 1300));
  
  document.querySelector('#phone-app-status').textContent = 'Đang gửi Webhook...';
  
  const orderMatch = content.match(/[A-Z0-9]{3,10}/i);
  const orderCode = orderMatch ? orderMatch[0].toUpperCase() : 'DEMO' + Math.random().toString(36).substring(2, 8).toUpperCase();
  
  const demoPayload = {
    "event_id": "evt_demo_" + Math.random().toString(36).substring(2, 12),
    "type": "bank.credit_alert",
    "livemode": false,
    "created_at": now.toISOString(),
    "data": {
      "tenant_id": 9,
      "tenant_name": "Test Tenant",
      "bank_name": bank,
      "amount": Number(amount),
      "currency": "VND",
      "direction": "in",
      "order_code": orderCode,
      "transfer_content": content,
      "confidence": 1.0
    }
  };
  
  document.querySelector('#terminal-payload').textContent = JSON.stringify(demoPayload, null, 2);
  document.querySelector('#phone-app-status').textContent = 'Webhook đã gửi thành công ✅';
  
  btn.disabled = false;
  btn.textContent = `Gửi mock ${formattedAmount}`;
  
  // Auto-hide notification after 4 seconds
  setTimeout(() => {
    notif.style.opacity = '0';
    setTimeout(() => {
      notif.style.display = 'none';
      notif.style.opacity = '';
    }, 300);
  }, 4000);
}

// Hook up landing page events
const landingLoginBtn = document.querySelector('#landing-login-btn');
if (landingLoginBtn) landingLoginBtn.addEventListener('click', navigateToLogin);

const heroStartBtn = document.querySelector('#hero-start-btn');
if (heroStartBtn) heroStartBtn.addEventListener('click', navigateToLogin);

const demoTriggerBtn = document.querySelector('#demo-trigger-btn');
if (demoTriggerBtn) demoTriggerBtn.addEventListener('click', triggerDemoTransfer);

document.querySelectorAll('.pricing-cta').forEach(btn => {
  btn.addEventListener('click', navigateToLogin);
});

// App Router
const path = window.location.pathname;
if (path === '/login') {
  if (localStorage.getItem('admin_token')) {
    window.history.pushState({}, '', '/admin');
    const loginType = localStorage.getItem('admin_user_type') || 'admin';
    document.querySelector('.nav-item[data-view="tenants"]').style.display = loginType === 'tenant' ? 'none' : '';
    showAuthView(false);
    loadDashboard();
    loadTenants().then(loadTenantWebhooks);
    startPolling();
  } else {
    showAuthView(true);
  }
} else if (path === '/admin') {
  if (localStorage.getItem('admin_token')) {
    const loginType = localStorage.getItem('admin_user_type') || 'admin';
    document.querySelector('.nav-item[data-view="tenants"]').style.display = loginType === 'tenant' ? 'none' : '';
    showAuthView(false);
    loadDashboard();
    loadTenants().then(loadTenantWebhooks);
    startPolling();
  } else {
    window.history.pushState({}, '', '/login');
    showAuthView(true);
  }
} else if (path === '/') {
  if (localStorage.getItem('admin_token')) {
    window.history.pushState({}, '', '/admin');
    const loginType = localStorage.getItem('admin_user_type') || 'admin';
    document.querySelector('.nav-item[data-view="tenants"]').style.display = loginType === 'tenant' ? 'none' : '';
    showAuthView(false);
    loadDashboard();
    loadTenants().then(loadTenantWebhooks);
    startPolling();
  } else {
    switchAppView('landing');
  }
} else {
  if (localStorage.getItem('admin_token')) {
    showAuthView(false);
  } else {
    switchAppView('landing');
  }
}

