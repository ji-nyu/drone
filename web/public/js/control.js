// ── 드론 관제 ──────────────────────────────────────────────
const STATES = [
  { id: 'requested', label: '요청됨',   icon: '📋', desc: '배송 임무 요청 접수' },
  { id: 'preparing', label: '출발준비', icon: '🔧', desc: '기체 점검 및 이륙 준비' },
  { id: 'flying',    label: '비행중',   icon: '🚁', desc: '목적지 비행 중' },
  { id: 'arrived',   label: '도착',     icon: '📍', desc: '목적지 도착' },
  { id: 'delivered', label: '배송완료', icon: '✅', desc: '화물 투하 완료' },
  { id: 'failed',    label: '배송실패', icon: '❌', desc: '배송 실패', isFail: true },
  { id: 'returning', label: '복귀중',   icon: '↩️', desc: '기지 복귀 중' },
];

let selectedDrone   = (window.PAGE_DATA && window.PAGE_DATA.selectedDrone) ? window.PAGE_DATA.selectedDrone : 'TT-01';
let ctrlMission     = null;
let ctrlStTs        = {};
let ctrlFlightStart = null;
let ctrlFlightTimer = null;
let ctrlMaxAlt      = 0;

// ── 초기화 ──
function buildSelect() {
  const sel = document.getElementById('droneSelect');
  if (!sel) return;
  DRONE_IDS.forEach(id => {
    const opt = document.createElement('option');
    opt.value = id; opt.textContent = id;
    if (id === selectedDrone) opt.selected = true;
    sel.appendChild(opt);
  });
}

function buildStateChain() {
  const chain = document.getElementById('stateChain');
  if (!chain) return;
  STATES.forEach(s => {
    const row = document.createElement('div');
    row.className = 'state-row'; row.id = 'st-' + s.id;
    row.innerHTML =
      `<div class="state-connector"></div>` +
      `<div class="state-node">${s.icon}</div>` +
      `<div class="state-body">` +
        `<div class="state-name">${s.label}</div>` +
        `<div class="state-desc">${s.desc}</div>` +
        `<div class="state-ts" id="st-ts-${s.id}"></div>` +
      `</div>`;
    chain.appendChild(row);
  });
}

// ── 드론 변경 ──
function changeDrone(id) {
  selectedDrone = id;
  resetMission();
  clearLog();
  stopVlmPolling();
  loadRecentLogs();
  history.replaceState(null, '', '/drone/control?drone=' + id);

  // 드론 변경 시 연결 상태 초기화
  if (_connected) disconnectDrone();
  else {
    const btnConn = document.getElementById('btnConnect');
    if (btnConn) { btnConn.disabled = false; btnConn.textContent = '● 연결'; }
  }
}

// ── 상태 적용 ──
function applyStatus(d) {
  const isOn = d.online !== false;
  const connEl = document.getElementById('ctrlConn');
  if (connEl) { connEl.textContent = isOn ? '● 연결됨' : '○ 미연결'; connEl.style.color = isOn ? '#22c55e' : '#9ca3af'; }


  if (d.battery != null) {
    const el = document.getElementById('ctrlBattery');
    if (el) el.innerHTML = `${d.battery}<span class="ctrl-stat-unit">%</span>`;
    const fill = document.getElementById('ctrlBatFill');
    if (fill) {
      fill.style.width      = d.battery + '%';
      fill.style.background = d.battery > 50 ? '#22c55e' : d.battery > 20 ? '#f59e0b' : '#ef4444';
    }
    const est   = document.getElementById('ctrlBatEst');
    if (est)   est.textContent = `약 ${Math.round(d.battery * 0.13)}분 추정`;
    const range = document.getElementById('ctrlBatRange');
    if (range) range.textContent = `편도 약 ${Math.round(d.battery * 13.5)} m`;
  }
  if (d.altitude != null) {
    const el = document.getElementById('ctrlAlt');
    if (el) el.innerHTML = `${d.altitude}<span class="ctrl-stat-unit">m</span>`;
    if (d.altitude > ctrlMaxAlt) {
      ctrlMaxAlt = d.altitude;
      const ma = document.getElementById('maxAlt');
      if (ma) ma.textContent = ctrlMaxAlt + ' m';
    }
  }
  const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  if (d.speed != null) setEl('ctrlSpeed', `속도 ${d.speed} cm/s`);
  if (d.temperature_low != null && d.temperature_high != null) {
    const avg = Math.round((d.temperature_low + d.temperature_high) / 2);
    const el = document.getElementById('ctrlTemp');
    if (el) el.innerHTML = `${avg}<span class="ctrl-stat-unit">°C</span>`;
    setEl('ctrlTempRange', `${d.temperature_low} / ${d.temperature_high}`);
  }
  if (d.yaw   != null) setEl('yaw', d.yaw + '°');
  if (d.pitch != null && d.roll != null) setEl('pitchRoll', `${d.pitch}° / ${d.roll}°`);
  if (d.lat   != null && d.lon  != null) {
    setEl('videoCoords', `LAT ${d.lat.toFixed(6)}  LON ${d.lon.toFixed(6)}`);
  }
  if (d.mission_id)    setEl('ctrlMissionId', 'ID: ' + d.mission_id);
  if (d.mission_state) setMissionState(d.mission_state);
}

// ── 임무 상태 머신 ──
function setMissionState(stateId) {
  if (ctrlMission === stateId) return;
  ctrlMission = stateId;
  const idx = STATES.findIndex(s => s.id === stateId);
  const now = new Date().toLocaleTimeString('ko-KR');

  STATES.forEach((s, i) => {
    const row = document.getElementById('st-' + s.id);
    const ts  = document.getElementById('st-ts-' + s.id);
    if (!row) return;
    row.classList.remove('done', 'active', 'fail');
    if (s.isFail && stateId === 'failed') {
      row.classList.add('active');
      if (!ctrlStTs[s.id]) { ctrlStTs[s.id] = now; if (ts) ts.textContent = now; }
    } else if (!s.isFail) {
      if (i < idx)        { row.classList.add('done');   if (!ctrlStTs[s.id]) { ctrlStTs[s.id]=now; if(ts)ts.textContent=now; } }
      else if (i === idx) { row.classList.add('active'); if (!ctrlStTs[s.id]) { ctrlStTs[s.id]=now; if(ts)ts.textContent=now; } }
    }
  });

  const label = STATES.find(s => s.id === stateId)?.label ?? stateId;
  const el = document.getElementById('ctrlMissionState');
  if (el) el.textContent = label;
  const stateMsg = `[${selectedDrone}] 상태: ${label}`;
  addLog(stateId === 'failed' ? 'error' : 'ok', stateMsg, 'logBody');
  saveLog(stateId === 'failed' ? 'error' : 'ok', stateMsg);

  const rb = document.getElementById('btnRetry');
  if (rb) rb.style.display = stateId === 'failed' ? '' : 'none';

  if (stateId === 'flying' && !ctrlFlightStart) {
    ctrlFlightStart = Date.now();
    ctrlFlightTimer = setInterval(() => {
      const s  = Math.floor((Date.now() - ctrlFlightStart) / 1000);
      const ft = document.getElementById('flightTime');
      if (ft) ft.textContent = String(Math.floor(s/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0');
    }, 1000);
  }
  if ((stateId === 'delivered' || stateId === 'failed') && ctrlFlightTimer) {
    clearInterval(ctrlFlightTimer); ctrlFlightTimer = null;
  }
}

function resetMission() {
  ctrlMission = null; ctrlStTs = {}; ctrlFlightStart = null; ctrlMaxAlt = 0;
  if (ctrlFlightTimer) { clearInterval(ctrlFlightTimer); ctrlFlightTimer = null; }
  STATES.forEach(s => {
    const row = document.getElementById('st-' + s.id);
    const ts  = document.getElementById('st-ts-' + s.id);
    if (row) row.classList.remove('done', 'active', 'fail');
    if (ts)  ts.textContent = '';
  });
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  set('flightTime',       '--:--');
  set('maxAlt',           '-- m');
  set('ctrlMissionState', '대기중');
  set('ctrlMissionId',    'ID: —');
  const rb = document.getElementById('btnRetry');
  if (rb) rb.style.display = 'none';
}

// ── VLM 분석 로그 (Python API 폴링) ──
let _vlmLastId = 0;
let _vlmPollTimer = null;
let _vlmLoadingLogged = false;

function vlmLogsUrl() {
  const base  = (CONFIG.API_BASE || '').replace(/\/+$/, '');
  const token = CONFIG.API_TOKEN || '';
  return `${base}/drones/${encodeURIComponent(selectedDrone)}/vlm/logs`
       + `?token=${encodeURIComponent(token)}&since=${_vlmLastId}&limit=50`;
}

async function pollVlmLogs() {
  if (!_connected) return;
  try {
    const res = await fetch(vlmLogsUrl(), { cache: 'no-store', signal: AbortSignal.timeout(5000) });
    if (!res.ok) return;
    const json = await res.json();
    if (!json.enabled) return;

    const status = json.status ?? {};
    if (!status.ready && status.worker_alive && !_vlmLoadingLogged) {
      _vlmLoadingLogged = true;
      addLog('info', 'VLM 모델 로드 중... (1~3분 소요, 완료 후 답변이 표시됩니다)', 'logBody');
    }

    for (const item of (json.items ?? [])) {
      if (item.id <= _vlmLastId) continue;
      _vlmLastId = item.id;
      const level = item.level || 'info';
      addLog(level, item.message, 'logBody');
      saveLog(level, item.message);
    }
  } catch { /* 다음 폴링에서 재시도 */ }
}

function startVlmPolling() {
  stopVlmPolling();
  _vlmLastId = 0;
  _vlmLoadingLogged = false;
  pollVlmLogs();
  _vlmPollTimer = setInterval(pollVlmLogs, 1000);
}

function stopVlmPolling() {
  if (_vlmPollTimer) { clearInterval(_vlmPollTimer); _vlmPollTimer = null; }
}

// ── 연결 / 해제 ──
let _connected = false;

async function connectDrone() {
  const btnConn = document.getElementById('btnConnect');
  const btnDisc = document.getElementById('btnDisconnect');
  btnConn.disabled = true; btnConn.textContent = '연결 중...';
  addLog('info', `[${selectedDrone}] 스트리밍 시작 요청`, 'logBody');

  try {
    const res = await fetch('/api/tello/stream/on', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ drone_id: selectedDrone }),
      signal:  AbortSignal.timeout(8000),
    });
    const d = await res.json();
    if (!res.ok) throw new Error(d.error?.message ?? 'HTTP ' + res.status);

    _connected = true;
    btnConn.style.display = 'none';
    btnDisc.style.display = '';
    addLog('ok', `[${selectedDrone}] 스트리밍 시작`, 'logBody');
    saveLog('ok', `[${selectedDrone}] 스트리밍 시작`);
    // streamon 직후 프레임 버퍼가 채워질 때까지 잠시 대기
    await new Promise(r => setTimeout(r, 800));
    tryVideo();
    startVlmPolling();
  } catch (e) {
    btnConn.disabled = false; btnConn.textContent = '● 연결';
    addLog('error', `[${selectedDrone}] 스트리밍 실패: ${e.message}`, 'logBody');
  }
}

async function disconnectDrone() {
  const btnConn = document.getElementById('btnConnect');
  const btnDisc = document.getElementById('btnDisconnect');
  btnDisc.disabled = true; btnDisc.textContent = '해제 중...';

  try {
    await fetch('/api/tello/stream/off', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ drone_id: selectedDrone }),
      signal:  AbortSignal.timeout(5000),
    });
  } catch {}

  _connected = false;
  stopVlmPolling();
  stopVideo();
  const ph = document.getElementById('videoPh');
  if (ph) ph.style.display = 'flex';

  btnDisc.disabled = false; btnDisc.style.display = 'none';
  btnConn.style.display = ''; btnConn.textContent = '● 연결';
  addLog('info', `[${selectedDrone}] 스트리밍 중지`, 'logBody');
  saveLog('info', `[${selectedDrone}] 스트리밍 중지`);
}

// ── 영상 (Python API 스냅샷 직접 폴링) ──
// MJPEG <img>는 8080→8000 크로스 오리진에서 첫 프레임만 보이고 멈추는 경우가 많다.
// test-web 과 동일하게 Python /snapshot.jpg 를 100ms 간격으로 직접 받는다.
let _snapRunning = false;
let _snapTimer    = null;

function snapshotUrl() {
  const base  = (CONFIG.API_BASE || '').replace(/\/+$/, '');
  const token = CONFIG.API_TOKEN || '';
  return `${base}/drones/${encodeURIComponent(selectedDrone)}/snapshot.jpg`
       + `?token=${encodeURIComponent(token)}&_=${Date.now()}`;
}

function setVideoFrame(img, blob) {
  const url = URL.createObjectURL(blob);
  if (img._prevBlobUrl) URL.revokeObjectURL(img._prevBlobUrl);
  img._prevBlobUrl = url;
  img.src = url;
}

function tryVideo() {
  stopVideo();

  const img   = document.getElementById('videoFeed');
  const ph    = document.getElementById('videoPh');
  const badge = document.getElementById('liveBadge');
  if (!img) return;

  let shown = false;
  let _snapReq = 0;
  _snapRunning = true;

  const tick = async () => {
    if (!_snapRunning || !_connected) return;
    const reqId = ++_snapReq;
    try {
      const r = await fetch(snapshotUrl(), { cache: 'no-store', signal: AbortSignal.timeout(1500) });
      if (!_snapRunning || reqId !== _snapReq) return;
      if (r.ok) {
        const blob = await r.blob();
        if (!_snapRunning || reqId !== _snapReq) return;
        if (blob.size > 0) {
          setVideoFrame(img, blob);
          if (!shown) {
            shown = true;
            img.style.display = 'block';
            if (ph)    ph.style.display    = 'none';
            if (badge) badge.style.display = 'block';
          }
        }
      }
    } catch { /* 다음 틱에서 재시도 */ }
  };

  tick();
  _snapTimer = setInterval(tick, 100);
}

function stopVideo() {
  _snapRunning = false;
  if (_snapTimer) { clearInterval(_snapTimer); _snapTimer = null; }
  const img = document.getElementById('videoFeed');
  const ph  = document.getElementById('videoPh');
  if (img) {
    if (img._prevBlobUrl) { URL.revokeObjectURL(img._prevBlobUrl); img._prevBlobUrl = null; }
    img.src = '';
    img.style.display = 'none';
  }
  if (ph) ph.style.display = '';
  const badge = document.getElementById('liveBadge');
  if (badge) badge.style.display = 'none';
}

// ── API 폴링 ──
async function pollControl() {
  try {
    const res  = await fetch(`/api/drones/${selectedDrone}/status`, {
      signal: AbortSignal.timeout(900),
    });
    if (!res.ok) throw new Error();
    const json = await res.json();
    applyStatus({ online: json.online, ...( json.telemetry ?? {}) });
  } catch {
    applyStatus({ online: false });
  }
}

// ── DB 로그 저장 / 조회 ──
function saveLog(level, message, missionId = null) {
  const body = { drone_id: selectedDrone, level, message };
  if (missionId) body.mission_id = missionId;
  fetch('/api/logs', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  }).catch(() => {});
}

async function loadRecentLogs() {
  try {
    const res  = await fetch(`/api/logs?drone_id=${encodeURIComponent(selectedDrone)}&limit=50`);
    const json = await res.json();
    const logs = (json.data ?? []).reverse();
    logs.forEach(l => addLog(l.level, l.message, 'logBody'));
  } catch {}
}

// ── 명령 ──
const CMD_TO_TELLO = {
  takeoff:   '/api/tello/takeoff',
  land:      '/api/tello/land',
  emergency: '/api/tello/emergency',
  hover:     '/api/tello/rc',
  return:    '/api/tello/land',
};
const CMD_BODY = {
  hover: { lr: 0, fb: 0, ud: 0, yaw: 0 },
};

async function droneCmd(cmd) {
  const url  = CMD_TO_TELLO[cmd];
  if (!url) { addLog('error', `[${selectedDrone}] 알 수 없는 명령: ${cmd}`, 'logBody'); return; }

  const msg = `[${selectedDrone}] 명령: ${cmd}`;
  addLog('info', msg, 'logBody');
  saveLog('info', msg);

  try {
    const res = await fetch(url, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ drone_id: selectedDrone, ...(CMD_BODY[cmd] ?? {}) }),
      signal:  AbortSignal.timeout(10000),
    });
    const d = await res.json();
    logApiEvent('POST', url, res.status, CMD_BODY[cmd] ?? {}, d);
    const result = `[${selectedDrone}][${cmd}] ${d.result?.ok != null ? (d.result.ok ? '성공' : '실패') : (res.ok ? '성공' : '실패')}`;
    addLog(res.ok ? 'ok' : 'error', result, 'logBody');
    saveLog(res.ok ? 'ok' : 'error', result);
  } catch (e) {
    const err = `[${selectedDrone}][${cmd}] 실패: ${e.message}`;
    addLog('error', err, 'logBody');
    saveLog('error', err);
  }
}

function clearLog() {
  const el = document.getElementById('logBody');
  if (el) el.innerHTML = '';
}

// ── 키보드 단축키 (명령) ──
document.addEventListener('keydown', e => {
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
  const disabled = id => document.getElementById(id)?.disabled;
  switch (e.key) {
    case 'T': case 't': if (!disabled('btnTakeoff'))   droneCmd('takeoff');   break;
    case 'L': case 'l': if (!disabled('btnLand'))      droneCmd('land');      break;
    case 'R': case 'r': if (!disabled('btnReturn'))    droneCmd('return');    break;
    case 'H': case 'h': if (!disabled('btnHover'))     droneCmd('hover');     break;
    case 'E': case 'e': if (e.shiftKey && !disabled('btnEmergency')) droneCmd('emergency'); break;
  }
});

// ── 키보드 RC 조작 ──
const _kbdPressed  = new Set();
const _kbdSpeed    = 40;
const _kbdKeyMap   = {
  KeyW: 'k-keyw', KeyS: 'k-keys', KeyA: 'k-keya', KeyD: 'k-keyd',
  ArrowUp: 'k-arrowup', ArrowDown: 'k-arrowdown',
  ArrowLeft: 'k-arrowleft', ArrowRight: 'k-arrowright',
};

function _kbdCompute() {
  let lr = 0, fb = 0, ud = 0, yaw = 0;
  if (_kbdPressed.has('ArrowLeft'))  lr  -= _kbdSpeed;
  if (_kbdPressed.has('ArrowRight')) lr  += _kbdSpeed;
  if (_kbdPressed.has('ArrowUp'))    fb  += _kbdSpeed;
  if (_kbdPressed.has('ArrowDown'))  fb  -= _kbdSpeed;
  if (_kbdPressed.has('KeyW'))       ud  += _kbdSpeed;
  if (_kbdPressed.has('KeyS'))       ud  -= _kbdSpeed;
  if (_kbdPressed.has('KeyA'))       yaw -= _kbdSpeed;
  if (_kbdPressed.has('KeyD'))       yaw += _kbdSpeed;
  return { lr, fb, ud, yaw };
}

function _kbdUpdateUi() {
  const v = _kbdCompute();
  const el = document.getElementById('kbdValues');
  if (el) el.textContent = `lr=${v.lr} fb=${v.fb} ud=${v.ud} yaw=${v.yaw}`;
  return v;
}

document.addEventListener('keydown', e => {
  if (!(e.code in _kbdKeyMap)) return;
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
  e.preventDefault();
  if (_kbdPressed.has(e.code)) return;
  _kbdPressed.add(e.code);
  document.getElementById(_kbdKeyMap[e.code])?.classList.add('on');
  _kbdUpdateUi();
});

document.addEventListener('keyup', e => {
  if (!(e.code in _kbdKeyMap)) return;
  e.preventDefault();
  _kbdPressed.delete(e.code);
  document.getElementById(_kbdKeyMap[e.code])?.classList.remove('on');
  _kbdUpdateUi();
});

let _rcLastZero = false;

setInterval(() => {
  const v = _kbdUpdateUi();
  if (!document.getElementById('kbdEnable')?.checked) return;
  const isZero = v.lr === 0 && v.fb === 0 && v.ud === 0 && v.yaw === 0;
  if (isZero && _rcLastZero) return;   // 이미 0 전송했으면 스킵
  _rcLastZero = isZero;
  fetch('/api/tello/rc', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ drone_id: selectedDrone, ...v }),
  }).catch(() => {});
}, 100);

// ── 진입점 ──
function init_control() {
  const apiEl = document.getElementById('apiEndpoint');
  if (apiEl) apiEl.textContent = CONFIG.API_BASE;
  buildSelect();
  buildStateChain();
  addLog('info', `관제 페이지 — ${selectedDrone} 선택됨`, 'logBody');
  addLog('info', 'T=이륙  L=착륙  R=귀환  H=정지비행  Shift+E=긴급정지', 'logBody');
  loadRecentLogs();
  pollControl();
  setInterval(pollControl, CONFIG.POLL_MS);
}
