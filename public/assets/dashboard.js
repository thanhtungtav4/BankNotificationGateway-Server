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
let liveMode = false;
let pollTimer = null;
let expiryTimer = null;
let toastTimer = null;

const titles = {
  overview: 'Tổng quan hệ thống',
  tenants: 'Quản lý khách hàng',
  devices: 'Thiết bị Android',
  events: 'Notification events',
  webhooks: 'Webhook delivery',
  setup: 'Pairing thiết bị'
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
        </tr>`).join('')
    : `<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px">Chưa có tenant</td></tr>`;

  document.querySelector('#device-cards').innerHTML = state.devices.length
    ? state.devices.map(item => `
        <article class="device-card">
          <strong>${item.name}</strong>
          <p>${item.tenant} · ${item.bank}</p>
          <p>${badge(item.status)} · Queue: ${item.queue}</p>
          <p>Last seen: ${item.seen}</p>
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
        <tr><td>${row.tenant}</td><td>${row.url}</td><td>${badge(row.status)}</td><td>${row.attempt}</td><td>${row.http}</td></tr>
      `).join('')
    : `<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:24px">Chưa có webhook delivery</td></tr>`;
}

async function api(path, options = {}) {
  const response = await fetch(path, {
    headers: { Accept: 'application/json', ...(options.headers ?? {}) },
    ...options
  });
  if (!response.ok) throw new Error(`HTTP ${response.status}`);
  return response.json();
}

async function loadDashboard() {
  try {
    state = await api('/api/dashboard/summary');
    setStatus('live', 'Đang lấy dữ liệu thật. Auto-refresh 5 giây.');
  } catch (error) {
    state = structuredClone(fallbackState);
    setStatus('fallback', `API chưa sẵn sàng: ${error.message}.`);
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

function showView(view) {
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
  document.querySelector('#webhook-form-status').textContent = 'Đang queue…';
  try {
    await api('/api/webhooks/test', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    form.reset();
    document.querySelector('#webhook-form-status').textContent = 'Đã queue';
    toast('Đã queue test webhook', 'ok');
    await loadDashboard();
  } catch (error) {
    document.querySelector('#webhook-form-status').textContent = `Lỗi: ${error.message}`;
    toast(`Test webhook thất bại: ${error.message}`, 'dead');
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

// ===== Init =====

document.querySelectorAll('[data-view]').forEach(button => button.addEventListener('click', () => showView(button.dataset.view)));
document.querySelectorAll('[data-view-jump]').forEach(button => button.addEventListener('click', () => showView(button.dataset.viewJump)));
document.querySelector('#refresh-button').addEventListener('click', loadDashboard);
document.querySelector('#make-token').addEventListener('click', createPairingPayload);
document.querySelector('#tenant-form').addEventListener('submit', createTenant);
document.querySelector('#webhook-form').addEventListener('submit', testWebhook);

document.addEventListener('click', async event => {
  const copyBtn = event.target.closest('[data-copy-target]');
  if (!copyBtn) return;
  const target = document.getElementById(copyBtn.dataset.copyTarget);
  if (!target) return;
  const ok = await copyText(target.textContent.trim());
  toast(ok ? 'Đã copy' : 'Copy thất bại', ok ? 'ok' : 'dead');
});

document.querySelector('#server-url').value = window.location.origin;
loadDashboard();
loadTenants();
startPolling();
