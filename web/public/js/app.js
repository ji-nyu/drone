// ── 구버전 localStorage 키 정리 ────────────────────────────
localStorage.removeItem('droneList');

// ── 공통 유틸 ──────────────────────────────────────────────
function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function addLog(level, msg, bodyId) {
  const ids = bodyId ? [bodyId] : ['logBody', 'fullLogBody'];
  ids.forEach(id => {
    const body = document.getElementById(id);
    if (!body) return;
    const now = new Date().toTimeString().slice(0, 8);
    const div = document.createElement('div');
    div.className = 'log-entry';
    div.innerHTML =
      `<span class="log-time">${now}</span>` +
      `<span class="log-lv ${level}">${level.toUpperCase()}</span>` +
      `<span class="log-msg">${escHtml(msg)}</span>`;
    body.prepend(div);
    while (body.children.length > 300) body.lastChild.remove();
  });
}

// ── 시계 ──────────────────────────────────────────────────
function updateClock() {
  const el = document.getElementById('clock');
  if (el) el.textContent = new Date().toTimeString().slice(0, 8);
}
setInterval(updateClock, 1000);
updateClock();

// ── 사이드바 드론 요약 (모든 페이지 공통) ─────────────────
let _sideApiOk = false;

async function fetchDroneSummary() {
  try {
    const res = await fetch('/api/drones', { signal: AbortSignal.timeout(900) });
    if (!res.ok) throw new Error();
    const json = await res.json();
    const list = json.data ?? [];

    const online  = list.filter(d => d.online).length;
    const flying  = list.filter(d => d.mission_state === 'flying').length;
    const offline = list.length - online;

    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    set('sumOnline',  online);
    set('sumFlying',  flying);
    set('sumOffline', offline);

    const nb = document.getElementById('navOnline');
    if (nb) { nb.textContent = online; nb.style.display = online > 0 ? '' : 'none'; }
    const nf = document.getElementById('navFlying');
    if (nf) { nf.textContent = flying; nf.style.display = flying > 0 ? '' : 'none'; }

    if (!_sideApiOk) {
      _sideApiOk = true;
      const dot   = document.getElementById('sideConnDot');
      const label = document.getElementById('sideConnLabel');
      if (dot)   dot.className = 'conn-dot on';
      if (label) label.textContent = 'API 서버 연결됨';
    }
    return list;
  } catch {
    if (_sideApiOk) {
      _sideApiOk = false;
      const dot   = document.getElementById('sideConnDot');
      const label = document.getElementById('sideConnLabel');
      if (dot)   dot.className = 'conn-dot';
      if (label) label.textContent = 'API 서버 미연결';
    }
    return null;
  }
}

fetchDroneSummary();
setInterval(fetchDroneSummary, 5000);

// ── API 이벤트 로그 (sessionStorage 유지) ──────────────────
function logApiEvent(method, path, status, reqBody, resBody) {
  try {
    const evts = JSON.parse(sessionStorage.getItem('_apiEvts') || '[]');
    evts.unshift({
      t:      new Date().toTimeString().slice(0, 8),
      method: method.toUpperCase(),
      path,
      status,
      req: reqBody  ? JSON.stringify(reqBody,  null, 2) : null,
      res: resBody  ? JSON.stringify(resBody,  null, 2) : null,
    });
    if (evts.length > 200) evts.length = 200;
    sessionStorage.setItem('_apiEvts', JSON.stringify(evts));
  } catch {}
}

// ── 페이지 진입점 디스패처 ─────────────────────────────────
if (typeof window.PAGE !== 'undefined') {
  const fn = window['init_' + window.PAGE];
  if (typeof fn === 'function') fn();
}
