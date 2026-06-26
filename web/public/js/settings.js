// ── 설정 ───────────────────────────────────────────────────
const STORAGE_KEY = 'droneControlConfig';

let droneList = [];

// ── 드론 목록 테이블 ─────────────────────────────────────
function renderDroneTable() {
  const wrap = document.getElementById('droneListWrap');
  if (droneList.length === 0) {
    wrap.innerHTML = '<p style="font-size:12px;color:#9ca3af;padding:12px 0 4px">등록된 드론이 없습니다. 아래에서 추가하세요.</p>';
    return;
  }
  const rows = droneList.map(d => `
    <tr id="drone-row-${escHtml(d.drone_id)}">
      <td><span class="drone-id">${escHtml(d.drone_id)}</span></td>
      <td id="drone-ip-cell-${escHtml(d.drone_id)}"
          style="font-variant-numeric:tabular-nums;color:#1d4ed8;font-size:12px">
        ${escHtml(d.ip)}
      </td>
      <td style="text-align:center;white-space:nowrap;display:flex;gap:4px;justify-content:center">
        <button class="btn-xs" onclick="startEditIp('${escHtml(d.drone_id)}','${escHtml(d.ip)}')">수정</button>
        <button class="btn-xs" style="color:#dc2626;border-color:#fca5a5"
          onclick="removeDrone('${escHtml(d.drone_id)}')">삭제</button>
      </td>
    </tr>`).join('');
  wrap.innerHTML = `
    <table class="data-table" style="margin:8px 0">
      <thead><tr><th>이름 / ID</th><th>IP 주소</th><th style="width:100px;text-align:center">액션</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>
    <div style="text-align:right;padding-bottom:8px">
      <button class="btn-xs" style="color:#dc2626;border-color:#fca5a5" onclick="clearAllDrones()">전체 삭제</button>
    </div>`;
}

function startEditIp(droneId, currentIp) {
  const cell = document.getElementById(`drone-ip-cell-${droneId}`);
  if (!cell) return;
  cell.innerHTML = `
    <input id="edit-ip-${escHtml(droneId)}" type="text" value="${escHtml(currentIp)}"
      style="width:130px;padding:2px 6px;font-size:12px;border:1px solid #3b82f6;border-radius:4px;outline:none"
      onkeydown="if(event.key==='Enter')saveIp('${escHtml(droneId)}');if(event.key==='Escape')fetchDroneList()">
    <button class="btn-xs" style="border-color:#3b82f6;color:#1d4ed8" onclick="saveIp('${escHtml(droneId)}')">저장</button>
    <button class="btn-xs" onclick="fetchDroneList()">취소</button>`;
  document.getElementById(`edit-ip-${droneId}`)?.focus();
}

async function saveIp(droneId) {
  const input = document.getElementById(`edit-ip-${droneId}`);
  if (!input) return;
  const ip = input.value.trim();
  if (!isValidIp(ip)) { input.focus(); alert('올바른 IPv4 주소를 입력하세요.'); return; }
  try {
    const res  = await fetch('/api/drones/' + encodeURIComponent(droneId) + '/update', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ ip }),
    });
    const json = await res.json();
    if (!res.ok) { alert(json.message ?? '수정 실패'); return; }
    await fetchDroneList();
  } catch (e) {
    alert('API 오류: ' + e.message);
  }
}

async function fetchDroneList() {
  try {
    const res  = await fetch('/api/drones');
    const json = await res.json();
    droneList  = json.data ?? [];
  } catch {
    droneList = [];
  }
  renderDroneTable();
}

async function addDrone() {
  const nameEl = document.getElementById('newDroneName');
  const ipEl   = document.getElementById('newDroneIp');
  const name   = nameEl.value.trim();
  const ip     = ipEl.value.trim();

  if (!name)          { nameEl.focus(); alert('드론 이름(ID)을 입력하세요.'); return; }
  if (!isValidIp(ip)) { ipEl.focus();   alert('올바른 IPv4 주소를 입력하세요.\n예: 192.168.1.10'); return; }

  try {
    const res  = await fetch('/api/drones', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ drone_id: name, ip, label: name }),
    });
    const json = await res.json();
    if (!res.ok) { alert(json.message ?? '등록 실패'); return; }
    nameEl.value = ''; ipEl.value = ''; nameEl.focus();
    await fetchDroneList();
  } catch (e) {
    alert('API 오류: ' + e.message);
  }
}

async function removeDrone(droneId) {
  if (!confirm(`'${droneId}' 드론을 삭제하시겠습니까?`)) return;
  try {
    const res  = await fetch('/api/drones/' + encodeURIComponent(droneId) + '/delete', { method: 'POST' });
    const json = await res.json();
    if (!res.ok) { alert(json.message ?? '삭제 실패'); return; }
    await fetchDroneList();
  } catch (e) {
    alert('API 오류: ' + e.message);
  }
}

async function clearAllDrones() {
  if (!confirm('등록된 드론을 모두 삭제하시겠습니까?')) return;
  for (const d of droneList) {
    await fetch('/api/drones/' + encodeURIComponent(d.drone_id) + '/delete', { method: 'POST' });
  }
  await fetchDroneList();
}

function isValidIp(ip) {
  if (!/^(\d{1,3}\.){3}\d{1,3}$/.test(ip)) return false;
  return ip.split('.').every(n => parseInt(n, 10) <= 255);
}

function _droneInputEnter(e) { if (e.key === 'Enter') addDrone(); }

// ── 설정 불러오기 / 저장 ────────────────────────────────
async function loadSettings() {
  const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
  document.getElementById('sStreamPath').value  = saved.STREAM_PATH  ?? CONFIG.STREAM_PATH;
  document.getElementById('sPollMs').value      = saved.POLL_MS      ?? CONFIG.POLL_MS;
  document.getElementById('sDroneCount').value  = saved.DRONE_COUNT  ?? CONFIG.DRONE_COUNT;
  document.getElementById('sDronePrefix').value = saved.DRONE_PREFIX ?? CONFIG.DRONE_PREFIX;

  try {
    const res  = await fetch('/api/settings/tello');
    const json = await res.json();
    if (res.ok && json.data) {
      document.getElementById('sTelloUrl').value     = json.data.api_url     ?? '';
      document.getElementById('sTelloToken').value   = json.data.api_token   ?? '';
      document.getElementById('sTelloTimeout').value = json.data.api_timeout ?? 10;
    }
  } catch {}
}

async function saveSettings() {
  const telloPayload = {
    api_url:     document.getElementById('sTelloUrl').value.trim(),
    api_token:   document.getElementById('sTelloToken').value.trim(),
    api_timeout: parseInt(document.getElementById('sTelloTimeout').value) || 10,
  };

  const cfg = {
    STREAM_PATH:  document.getElementById('sStreamPath').value.trim(),
    POLL_MS:      Math.max(500, parseInt(document.getElementById('sPollMs').value) || 1000),
    DRONE_COUNT:  Math.min(20, Math.max(1, parseInt(document.getElementById('sDroneCount').value) || 10)),
    DRONE_PREFIX: document.getElementById('sDronePrefix').value.trim() || 'TT',
  };

  try {
    const res = await fetch('/api/settings/tello', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(telloPayload),
    });
    if (!res.ok) {
      const d = await res.json();
      alert('저장 실패: ' + (d.message ?? 'HTTP ' + res.status));
      return;
    }
  } catch(e) {
    alert('저장 실패: ' + e.message);
    return;
  }

  localStorage.setItem(STORAGE_KEY, JSON.stringify(cfg));
  Object.assign(CONFIG, cfg);
  alert('설정이 저장되었습니다.\n페이지를 새로고침하면 적용됩니다.');
}

async function testConn() {
  const el    = document.getElementById('connTestResult');
  const base  = document.getElementById('sTelloUrl').value.trim().replace(/\/+$/, '');
  const token = document.getElementById('sTelloToken').value.trim();
  el.textContent = '연결 중...'; el.style.color = '#6b7280';

  if (!base) {
    el.textContent = 'Python API 서버 주소를 입력하세요. (예: http://127.0.0.1:8000)';
    el.style.color = '#dc2626';
    return;
  }

  try {
    const health = await fetch(base + '/health', { signal: AbortSignal.timeout(3000) });
    if (!health.ok) {
      el.textContent = `서버 응답 오류: HTTP ${health.status}`;
      el.style.color = '#dc2626';
      return;
    }
  } catch (e) {
    el.textContent = `연결 실패: ${e.message} (서버가 실행 중인지, 주소가 맞는지 확인하세요)`;
    el.style.color = '#dc2626';
    return;
  }

  try {
    const res = await fetch(base + '/drones', {
      headers: token ? { Authorization: token } : {},
      signal:  AbortSignal.timeout(3000),
    });
    if (res.status === 401) {
      el.textContent = '서버 연결됨 — 단, Authorization 토큰이 올바르지 않습니다.';
      el.style.color = '#d97706';
      return;
    }
    if (!res.ok) {
      el.textContent = `서버 연결됨 — 드론 목록 응답 오류: HTTP ${res.status}`;
      el.style.color = '#d97706';
      return;
    }
    const json  = await res.json();
    const count = (json.items ?? json.data ?? []).length;
    el.textContent = `연결 성공 — 드론 ${count}대 등록됨`;
    el.style.color = '#15803d';
  } catch (e) {
    el.textContent = `서버 연결됨 — 드론 목록 조회 실패: ${e.message}`;
    el.style.color = '#d97706';
  }
}

function init_settings() {
  loadSettings();
  fetchDroneList();
  document.getElementById('newDroneName').addEventListener('keydown', _droneInputEnter);
  document.getElementById('newDroneIp').addEventListener('keydown', _droneInputEnter);
}
