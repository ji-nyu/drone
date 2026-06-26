// ── 비행 로그 ──────────────────────────────────────────────
let allLogs    = [];
let logOffset  = 0;
const LOG_PAGE = 100;

function switchTab(tab) {
  const isFlight = tab === 'flight';
  document.getElementById('tab-flight').classList.toggle('active',  isFlight);
  document.getElementById('tab-api').classList.toggle('active',    !isFlight);
  document.getElementById('panelFlight').style.display  = isFlight ? '' : 'none';
  document.getElementById('panelApi').style.display     = isFlight ? 'none' : '';
  document.getElementById('logFilterBar').style.display = isFlight ? 'flex' : 'none';
  document.getElementById('apiFilterBar').style.display = isFlight ? 'none' : 'flex';
  if (!isFlight) renderApiEvents();
}

async function clearFullLogs() {
  const droneId = document.getElementById('logDroneFilter').value;
  const url = '/api/logs/clear' + (droneId ? '?drone_id=' + encodeURIComponent(droneId) : '');
  await fetch(url, { method: 'POST' }).catch(() => {});
  allLogs = [];
  logOffset = 0;
  document.getElementById('fullLogBody').innerHTML = '';
}

function applyFilter() {
  fetchFullLogs(true);
}

function renderLogs() {
  const body = document.getElementById('fullLogBody');
  body.innerHTML = '';
  allLogs.forEach(l => appendLogRow(l, body));
  renderMoreBtn();
}

function appendLogRow(l, body) {
  const div = document.createElement('div');
  div.className = 'log-entry';
  div.innerHTML =
    `<span class="log-time">${escHtml(l.logged_at ?? '')}</span>` +
    `<span class="log-lv ${l.level}">${l.level.toUpperCase()}</span>` +
    `<span class="log-msg">${escHtml(l.message ?? '')}</span>`;
  body.appendChild(div);
}

function renderMoreBtn() {
  const body   = document.getElementById('fullLogBody');
  const exists = document.getElementById('logMoreBtn');
  if (exists) exists.remove();
  if (allLogs.length === 0 || allLogs.length % LOG_PAGE !== 0) return;
  const btn = document.createElement('div');
  btn.id = 'logMoreBtn';
  btn.style.cssText = 'text-align:center;padding:12px 0';
  btn.innerHTML = '<button class="btn-xs" onclick="loadMoreLogs()">더 보기</button>';
  body.appendChild(btn);
}

async function fetchFullLogs(reset = true) {
  if (reset) { allLogs = []; logOffset = 0; }
  const droneId = document.getElementById('logDroneFilter').value;
  const level   = document.getElementById('logLevelFilter').value;
  let url = `/api/logs?limit=${LOG_PAGE}&offset=${logOffset}`;
  if (droneId) url += '&drone_id=' + encodeURIComponent(droneId);
  if (level)   url += '&level='    + encodeURIComponent(level);
  try {
    const res  = await fetch(url, { signal: AbortSignal.timeout(2000) });
    if (!res.ok) return;
    const json = await res.json();
    const rows = json.data ?? [];
    allLogs = reset ? rows : [...allLogs, ...rows];
    logOffset = allLogs.length;
    renderLogs();
  } catch { /* silent */ }
}

async function loadMoreLogs() {
  await fetchFullLogs(false);
}

// ── API 이벤트 패널 ──────────────────────────────────────
function clearApiEvents() {
  sessionStorage.removeItem('_apiEvts');
  renderApiEvents();
}

function toggleRaw(id) {
  document.getElementById(id).classList.toggle('expanded');
}

function renderApiEvents() {
  const body = document.getElementById('apiEventBody');
  const evts = JSON.parse(sessionStorage.getItem('_apiEvts') || '[]');
  if (!evts.length) {
    body.innerHTML = `
      <div style="text-align:center;color:#9ca3af;padding:60px 0;font-size:12px">
        API 이벤트 없음<br>
        <small style="font-size:11px;margin-top:6px;display:block">
          임무 생성, 드론 명령 등 API 호출 시 여기에 기록됩니다
        </small>
      </div>`;
    return;
  }
  body.innerHTML = evts.map((e, i) => {
    const isOk = e.status >= 200 && e.status < 300;
    const mCls = e.method === 'POST' ? 'post' : 'get';
    const sCls = isOk ? 'ok' : 'err';
    const reqId = `ae-req-${i}`, resId = `ae-res-${i}`;
    return `
      <div class="api-event-row">
        <div style="display:flex;align-items:center;gap:4px">
          <span class="api-method ${mCls}">${escHtml(e.method)}</span>
          <span style="font-size:11px;color:#374151;font-weight:500;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(e.path)}</span>
          <span class="api-status ${sCls}">${e.status}</span>
          <span style="font-size:10px;color:#9ca3af;margin-left:8px;flex-shrink:0">${escHtml(e.t)}</span>
        </div>
        ${e.req ? `<div class="api-raw" id="${reqId}" onclick="toggleRaw('${reqId}')"><span style="color:#6b7280;font-weight:600">REQ </span>${escHtml(e.req)}</div>` : ''}
        ${e.res ? `<div class="api-raw" id="${resId}" onclick="toggleRaw('${resId}')"><span style="color:#6b7280;font-weight:600">RES </span>${escHtml(e.res)}</div>` : ''}
      </div>`;
  }).join('');
}

function init_logs() {
  const sel = document.getElementById('logDroneFilter');
  DRONE_IDS.forEach(id => {
    const opt = document.createElement('option');
    opt.value = id; opt.textContent = id;
    sel.appendChild(opt);
  });
  fetchFullLogs(true);
  setInterval(() => fetchFullLogs(true), 5000);
}
