// ── 임무 현황 ──────────────────────────────────────────────
const MISSION_STATE_BADGE = { draft:'gray', requested:'gray', preparing:'yellow', flying:'blue', arrived:'blue', delivered:'green', failed:'red', returning:'yellow' };
const MISSION_STATE_KO    = { draft:'초안', requested:'요청됨', preparing:'출발준비', flying:'비행중', arrived:'도착', delivered:'배송완료', failed:'실패', returning:'복귀중' };
const MISSION_STATE_STEPS = ['requested', 'preparing', 'flying', 'arrived', 'delivered', 'returning'];

const ACTION_DEFS = {
  takeoff:    { label:'이륙',      unit:null, def:null },
  land:       { label:'착륙',      unit:null, def:null },
  forward:    { label:'앞으로',    unit:'cm', min:20, max:500, def:100 },
  back:       { label:'뒤로',      unit:'cm', min:20, max:500, def:100 },
  left:       { label:'좌로',      unit:'cm', min:20, max:500, def:100 },
  right:      { label:'우로',      unit:'cm', min:20, max:500, def:100 },
  up:         { label:'위로',      unit:'cm', min:20, max:500, def:50  },
  down:       { label:'아래로',    unit:'cm', min:20, max:500, def:50  },
  rotate_cw:  { label:'우회전',    unit:'°',  min:1,  max:360, def:90  },
  rotate_ccw: { label:'좌회전',    unit:'°',  min:1,  max:360, def:90  },
  hover:      { label:'정지비행',  unit:'초', min:1,  max:30,  def:3   },
  deliver:    { label:'배송투하',  unit:null, def:null },
};

const CHECKLIST_ITEMS = [
  { id:'battery', label:'배터리 잔량 30% 이상' },
  { id:'conn',    label:'드론 연결 상태 확인' },
  { id:'area',    label:'비행 구역 장애물 없음 확인' },
  { id:'video',   label:'영상 피드 정상 수신 확인' },
  { id:'home',    label:'복귀 지점 설정 완료' },
];

// ── 패널 전환 ────────────────────────────────────────────
function showEmptyPanel() {
  stopDetailPoll();
  document.getElementById('mPanelEmpty').style.display  = 'flex';
  document.getElementById('mPanelCreate').style.display = 'none';
  document.getElementById('mPanelDetail').style.display = 'none';
}
function showCreateForm() {
  document.getElementById('mPanelEmpty').style.display  = 'none';
  document.getElementById('mPanelCreate').style.display = 'flex';
  document.getElementById('mPanelDetail').style.display = 'none';
  initCreateForm();
}
function showDetailPanel() {
  document.getElementById('mPanelEmpty').style.display  = 'none';
  document.getElementById('mPanelCreate').style.display = 'none';
  document.getElementById('mPanelDetail').style.display = 'flex';
}

// ── 미션 생성 폼 ──────────────────────────────────────────
let mActions = [];

function initCreateForm() {
  const sel = document.getElementById('mDroneSelect');
  sel.innerHTML = '<option value="">— 드론 선택 —</option>';
  DRONE_IDS.forEach(id => {
    const o = document.createElement('option');
    o.value = id;
    o.textContent = DRONE_MAP[id] ? `${id}  (${DRONE_MAP[id]})` : id;
    sel.appendChild(o);
  });
  document.getElementById('mRangeInfo').style.display = 'none';
  mActions = [];
  renderActions();
  buildChecklist();
  updateSubmitBtn();
}

function onDroneSelect(id) {
  const ri = document.getElementById('mRangeInfo');
  if (!id) { ri.style.display = 'none'; return; }
  fetch(`/api/drones/${id}/status`, { signal: AbortSignal.timeout(1500) })
    .then(r => r.ok ? r.json() : null)
    .then(d => {
      if (!d?.telemetry || d.telemetry.battery == null) return;
      const bat   = d.telemetry.battery;
      const range = Math.round(bat * 13.5);
      document.getElementById('mBatPct').textContent    = bat + '%';
      document.getElementById('mRangeDist').textContent = range + ' m';
      const fill = document.getElementById('mRangeFill');
      fill.style.width      = bat + '%';
      fill.style.background = bat > 50 ? '#22c55e' : bat > 30 ? '#f59e0b' : '#ef4444';
      document.getElementById('mRangeWarn').style.display = bat < 30 ? '' : 'none';
      ri.style.display = '';
    }).catch(() => {});
}

function addAction(type) {
  type = type ?? 'forward';
  const def = ACTION_DEFS[type];
  mActions.push({ type, value: def?.def ?? null });
  renderActions();
}
function addPreset(type) { addAction(type); }

function removeAction(i) {
  mActions.splice(i, 1);
  renderActions();
}
function onActionTypeChange(i, type) {
  const def = ACTION_DEFS[type];
  mActions[i].type  = type;
  mActions[i].value = def?.def ?? null;
  renderActions();
}

function renderActions() {
  const list  = document.getElementById('mActionList');
  const empty = document.getElementById('mActionEmpty');
  empty.style.display = mActions.length ? 'none' : '';
  list.innerHTML = mActions.map((a, i) => {
    const def     = ACTION_DEFS[a.type];
    const selOpts = Object.entries(ACTION_DEFS)
      .map(([k, v]) => `<option value="${k}"${k === a.type ? ' selected' : ''}>${v.label}</option>`).join('');
    const valInput = def?.unit
      ? `<input class="action-val" type="number" value="${a.value ?? def.def}"
           min="${def.min}" max="${def.max}"
           oninput="mActions[${i}].value=+this.value">
         <span class="action-unit">${def.unit}</span>`
      : `<span style="width:90px;flex-shrink:0"></span>`;
    return `
      <div class="action-row">
        <span style="font-size:10px;font-weight:700;color:#9ca3af;width:16px;text-align:center;flex-shrink:0">${i+1}</span>
        <select class="action-select" onchange="onActionTypeChange(${i}, this.value)">${selOpts}</select>
        ${valInput}
        <button class="btn-xs" style="color:#dc2626;border-color:#fca5a5;flex-shrink:0" onclick="removeAction(${i})">✕</button>
      </div>`;
  }).join('');
}

function buildChecklist() {
  document.getElementById('mChecklist').innerHTML = CHECKLIST_ITEMS.map(c => `
    <div class="checklist-item" id="cl-${c.id}">
      <input type="checkbox" class="checklist-check" id="chk-${c.id}" onchange="onCheckChange()">
      <label for="chk-${c.id}" class="checklist-label">${c.label}</label>
    </div>`).join('');
}
function onCheckChange() {
  CHECKLIST_ITEMS.forEach(c => {
    document.getElementById('cl-' + c.id)
      .classList.toggle('checked', document.getElementById('chk-' + c.id).checked);
  });
  updateSubmitBtn();
}
function updateSubmitBtn() {
  const allOk = CHECKLIST_ITEMS.every(c => document.getElementById('chk-' + c.id)?.checked);
  document.getElementById('mSubmitBtn').disabled       = !allOk;
  document.getElementById('mSubmitHint').style.display = allOk ? 'none' : '';
}

async function submitMission() {
  const droneId = document.getElementById('mDroneSelect').value;
  if (!droneId)        { alert('드론을 선택하세요.');          return; }
  if (!mActions.length){ alert('액션을 1개 이상 추가하세요.'); return; }

  const payload = {
    drone_id: droneId,
    actions:  mActions.map(a => {
      const o = { type: a.type };
      if (ACTION_DEFS[a.type]?.unit) o.value = Number(a.value);
      return o;
    }),
  };

  const btn = document.getElementById('mSubmitBtn');
  btn.disabled = true; btn.textContent = '저장 중...';

  try {
    const res  = await fetch(`/api/missions`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: AbortSignal.timeout(5000),
    });
    const data = await res.json();
    logApiEvent('POST', '/api/missions', res.status, payload, data);
    if (res.ok) {
      refreshMissions();
      loadDetail(data.mission_id);
    } else {
      alert(`미션 저장 실패: ${data.message ?? 'HTTP ' + res.status}`);
      btn.disabled = false; btn.textContent = '임시 저장';
    }
  } catch(e) {
    alert(`요청 실패: ${e.message}`);
    btn.disabled = false; btn.textContent = '임시 저장';
  }
}

// ── 미션 목록 ────────────────────────────────────────────
function renderMissions(list) {
  const tbody = document.getElementById('missionBody');
  if (!list || !list.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:40px 0">임무 데이터 없음</td></tr>';
    return;
  }
  tbody.innerHTML = list.map(m => `
    <tr style="cursor:pointer" onclick="loadDetail(${m.id ?? ''})">
      <td><strong>${escHtml(String(m.id ?? '—'))}</strong></td>
      <td>
        <a href="/drone/control?drone=${encodeURIComponent(m.drone_id ?? '')}"
           style="color:#1d4ed8;text-decoration:none"
           onclick="event.stopPropagation()">${escHtml(m.drone_id ?? '—')}</a>
      </td>
      <td><span class="badge ${MISSION_STATE_BADGE[m.state] ?? 'gray'}">${MISSION_STATE_KO[m.state] ?? escHtml(m.state ?? '—')}</span></td>
      <td style="font-size:11px;color:#6b7280">${m.action_count != null ? m.action_count + '개' : '—'}</td>
      <td style="font-size:11px">${escHtml(m.started_at  ?? '—')}</td>
      <td style="font-size:11px">${escHtml(m.ended_at ?? '—')}</td>
    </tr>`).join('');
}

async function refreshMissions() {
  const droneId = document.getElementById('missionDroneFilter')?.value ?? '';
  const url = '/api/missions' + (droneId ? '?drone_id=' + encodeURIComponent(droneId) : '');
  try {
    const res  = await fetch(url, { signal: AbortSignal.timeout(2000) });
    if (!res.ok) throw new Error();
    const json = await res.json();
    renderMissions(json.data ?? []);
  } catch { renderMissions(null); }
}

// ── 미션 상세 ────────────────────────────────────────────
let currentMissionId  = null;
let detailPollTimer   = null;
const ACTIVE_STATES   = new Set(['draft', 'requested', 'preparing', 'flying', 'arrived', 'returning']);

function startDetailPoll() {
  stopDetailPoll();
  detailPollTimer = setInterval(() => {
    if (currentMissionId) loadDetail(currentMissionId, true);
  }, 4000);
}
function stopDetailPoll() {
  if (detailPollTimer) { clearInterval(detailPollTimer); detailPollTimer = null; }
}

async function loadDetail(id, silent = false) {
  if (!id) return;
  currentMissionId = id;
  showDetailPanel();
  document.getElementById('mDetailTitle').textContent      = `임무 #${id}`;
  document.getElementById('dMissionId').textContent        = id;
  document.getElementById('dDroneId').textContent          = '—';
  document.getElementById('dStartedAt').textContent        = '—';
  document.getElementById('dEndedAt').textContent          = '—';
  document.getElementById('dTimeline').innerHTML           = '<div style="font-size:12px;color:#9ca3af">로딩 중...</div>';
  document.getElementById('dActionSection').style.display  = 'none';
  document.getElementById('dStartSection').style.display   = 'none';
  document.getElementById('dRetrySection').style.display   = 'none';
  document.getElementById('dConfirmSection').style.display = 'none';

  try {
    const res  = await fetch(`/api/missions/${encodeURIComponent(id)}`, { signal: AbortSignal.timeout(2000) });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    renderDetail(json.data ?? json);
    if (ACTIVE_STATES.has((json.data ?? json).state)) {
      startDetailPoll();
    } else {
      stopDetailPoll();
    }
  } catch(e) {
    if (!silent) {
      document.getElementById('dTimeline').innerHTML =
        `<div style="font-size:12px;color:#9ca3af">데이터 로드 실패: ${escHtml(e.message)}</div>`;
    }
  }
}

function renderDetail(m) {
  document.getElementById('mDetailTitle').textContent = `임무 #${m.id}`;
  document.getElementById('dMissionId').textContent   = m.id       ?? '—';
  document.getElementById('dDroneId').textContent     = m.drone_id ?? '—';
  document.getElementById('dStartedAt').textContent   = m.started_at ?? '—';
  document.getElementById('dEndedAt').textContent     = m.ended_at   ?? '—';
  document.getElementById('dConfirmSection').style.display =
    ['arrived', 'flying'].includes(m.state) ? '' : 'none';

  const eventMap = {};
  (m.events ?? []).forEach(e => { eventMap[e.state] = e.timestamp; });
  const steps  = m.state === 'failed' ? [...MISSION_STATE_STEPS, 'failed'] : MISSION_STATE_STEPS;
  const curIdx = steps.indexOf(m.state);

  let timelineHtml = steps.map((s, i) => {
    const isDone   = i < curIdx && s !== 'failed';
    const isActive = i === curIdx;
    const isFail   = s === 'failed';
    const dotCls   = isDone ? 'done' : isActive ? (isFail ? 'fail' : 'active') : '';
    const rowCls   = isDone ? 'done' : isActive ? 'active' : '';
    const isLast   = i === steps.length - 1;
    const ts       = eventMap[s] ? `<div class="timeline-ts">${escHtml(eventMap[s])}</div>` : '';
    const extra    = isFail && m.fail_reason
      ? `<div style="font-size:11px;color:#ef4444;margin-top:2px">${escHtml(m.fail_reason)}</div>` : '';
    return `
      <div class="timeline-row ${rowCls}">
        ${!isLast ? '<div class="timeline-line"></div>' : ''}
        <div class="timeline-dot ${dotCls}"></div>
        <div class="timeline-body">
          <div class="timeline-state">${MISSION_STATE_KO[s] ?? escHtml(s)}</div>
          ${ts}${extra}
        </div>
      </div>`;
  }).join('');

  if (m.delivery_confirmed != null) {
    const ok = m.delivery_confirmed === true;
    timelineHtml += `
      <div style="margin-top:8px;font-size:11px;padding:7px 10px;border-radius:6px;
                  background:${ok ? '#f0fdf4' : '#fef2f2'};color:${ok ? '#15803d' : '#dc2626'}">
        ${ok ? '✓ 배송 완료 이벤트 서버 반영 확인됨' : '✗ 배송 완료 이벤트 서버 미반영'}
        ${m.delivery_confirmed_at
          ? `<span style="color:#9ca3af;margin-left:6px">${escHtml(m.delivery_confirmed_at)}</span>` : ''}
      </div>`;
  }
  document.getElementById('dTimeline').innerHTML = timelineHtml;

  const resultActions = m.actions ?? [];
  if (m.state === 'draft') {
    document.getElementById('dActionSection').style.display = '';
    renderDraftActions(resultActions);
  } else if (resultActions.length) {
    document.getElementById('dActionSection').style.display = '';
    document.getElementById('dActionList').innerHTML = resultActions.map((a, i) => {
      const def   = ACTION_DEFS[a.type];
      const label = def ? def.label : escHtml(a.type);
      const val   = def?.unit && a.value != null ? ` ${a.value}${def.unit}` : '';
      const st    = a.result === 'ok'   ? '<span class="action-result-st ok">완료</span>'
                  : a.result === 'fail' ? '<span class="action-result-st fail">실패</span>'
                  : a.result === 'skip' ? '<span class="action-result-st skip">건너뜀</span>'
                  : '';
      const err   = a.error ? `<div style="font-size:10px;color:#ef4444;margin-top:2px">${escHtml(a.error)}</div>` : '';
      return `
        <div class="action-result">
          <span class="action-result-num">${i+1}</span>
          <span class="action-result-name">${label}${val ? `<span style="color:#9ca3af"> ${val}</span>` : ''}</span>
          ${st}
        </div>${err}`;
    }).join('');
  }

  document.getElementById('dStartSection').style.display  = m.state === 'draft'  ? '' : 'none';
  document.getElementById('dRetrySection').style.display  = m.state === 'failed' ? '' : 'none';
  if (m.state === 'failed') {
    document.getElementById('dFailReason').textContent = m.fail_reason ?? '원인 불명';
  }
}

// ── draft 편집 ────────────────────────────────────────────
let draftActions = [];

function renderDraftActions(actions) {
  draftActions = actions.map(a => ({ type: a.type, value: a.value }));
  flushDraftActions();
}

function flushDraftActions() {
  const list = document.getElementById('dActionList');
  list.innerHTML = `
    <div id="dActionRows">
      ${draftActions.map((a, i) => draftActionRow(a, i)).join('')}
    </div>
    <div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:8px">
      <button class="btn-xs" onclick="dAddPreset('takeoff')">이륙</button>
      <button class="btn-xs" onclick="dAddPreset('forward')">앞으로</button>
      <button class="btn-xs" onclick="dAddPreset('deliver')">배송투하</button>
      <button class="btn-xs" onclick="dAddPreset('land')">착륙</button>
      <button class="btn-xs" onclick="dAddAction()">+ 액션 추가</button>
    </div>
    <button class="btn primary" style="width:100%;margin-top:10px" onclick="saveDraft()">액션 저장</button>`;
}

function draftActionRow(a, i) {
  const def     = ACTION_DEFS[a.type];
  const selOpts = Object.entries(ACTION_DEFS)
    .map(([k, v]) => `<option value="${k}"${k === a.type ? ' selected' : ''}>${v.label}</option>`).join('');
  const valInput = def?.unit
    ? `<input class="action-val" type="number" value="${a.value ?? def.def}" min="${def.min}" max="${def.max}"
         oninput="draftActions[${i}].value=+this.value">
       <span class="action-unit">${def.unit}</span>`
    : `<span style="width:90px;flex-shrink:0"></span>`;
  return `
    <div class="action-row">
      <span style="font-size:10px;font-weight:700;color:#9ca3af;width:16px;text-align:center;flex-shrink:0">${i+1}</span>
      <select class="action-select" onchange="dOnTypeChange(${i},this.value)">${selOpts}</select>
      ${valInput}
      <button class="btn-xs" style="color:#dc2626;border-color:#fca5a5;flex-shrink:0" onclick="dRemoveAction(${i})">✕</button>
    </div>`;
}

function dAddAction(type) {
  type = type ?? 'forward';
  draftActions.push({ type, value: ACTION_DEFS[type]?.def ?? null });
  flushDraftActions();
}
function dAddPreset(type) { dAddAction(type); }
function dRemoveAction(i) { draftActions.splice(i, 1); flushDraftActions(); }
function dOnTypeChange(i, type) {
  draftActions[i] = { type, value: ACTION_DEFS[type]?.def ?? null };
  flushDraftActions();
}

async function saveDraft() {
  if (!currentMissionId) return;
  const payload = {
    actions: draftActions.map(a => {
      const o = { type: a.type };
      if (ACTION_DEFS[a.type]?.unit) o.value = Number(a.value);
      return o;
    }),
  };
  try {
    const res  = await fetch(`/api/missions/${encodeURIComponent(currentMissionId)}/update`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: AbortSignal.timeout(3000),
    });
    const data = await res.json();
    if (res.ok) {
      loadDetail(currentMissionId);
      refreshMissions();
    } else {
      alert(`저장 실패: ${data.message ?? 'HTTP ' + res.status}`);
    }
  } catch(e) {
    alert('요청 실패: ' + e.message);
  }
}

async function startMission() {
  if (!currentMissionId) return;
  if (!confirm(`임무 #${currentMissionId}을(를) 실행하시겠습니까?\n액션이 순서대로 드론에 전송됩니다.`)) return;

  const btn = document.getElementById('dRunBtn');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ 실행 중...'; }

  try {
    const res  = await fetch(`/api/missions/${encodeURIComponent(currentMissionId)}/run`, {
      method: 'POST', signal: AbortSignal.timeout(180000),
    });
    const data = await res.json();
    if (res.ok) {
      const failed = data.status === 'fail';
      const lines  = (data.results ?? []).map(r => {
        const icon = r.result === 'ok' ? '✓' : r.result === 'fail' ? '✗' : '–';
        return `${icon} ${r.type}${r.error ? ' — ' + r.error : ''}`;
      }).join('\n');
      alert(`미션 ${failed ? '실패' : '완료'}\n\n${lines}`);
    } else {
      alert(`실행 실패: ${data.message ?? 'HTTP ' + res.status}`);
    }
  } catch(e) {
    alert('요청 실패: ' + e.message);
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '▶ 임무 실행'; }
    loadDetail(currentMissionId);
    refreshMissions();
  }
}

async function deleteMission() {
  if (!currentMissionId) return;
  if (!confirm(`임무 #${currentMissionId}을(를) 삭제하시겠습니까?`)) return;
  try {
    const res  = await fetch(`/api/missions/${encodeURIComponent(currentMissionId)}/delete`, {
      method: 'POST', signal: AbortSignal.timeout(3000),
    });
    const data = await res.json();
    if (res.ok) {
      showEmptyPanel();
      refreshMissions();
    } else {
      alert(data.message ?? '삭제 실패');
    }
  } catch(e) {
    alert('요청 실패: ' + e.message);
  }
}

async function retryMission() {
  if (!currentMissionId) return;
  if (!confirm(`임무 ${currentMissionId}을(를) 동일 액션으로 재시도하시겠습니까?`)) return;
  try {
    const res  = await fetch(`/api/missions/${encodeURIComponent(currentMissionId)}/retry`, {
      method: 'POST', signal: AbortSignal.timeout(3000),
    });
    const data = await res.json();
    logApiEvent('POST', `/api/missions/${currentMissionId}/retry`, res.status, null, data);
    if (res.ok) {
      alert(`재시도 미션 생성 완료 — 새 ID: ${data.mission_id ?? '—'}`);
      showEmptyPanel();
      refreshMissions();
    } else {
      alert(`재시도 실패: ${data.message ?? 'HTTP ' + res.status}`);
    }
  } catch(e) {
    alert(`요청 실패: ${e.message}`);
  }
}

async function confirmDelivery() {
  if (!currentMissionId) return;
  if (!confirm('배송 완료로 수동 처리하시겠습니까?')) return;
  try {
    const res  = await fetch(`/api/missions/${encodeURIComponent(currentMissionId)}/confirm`, {
      method: 'POST', signal: AbortSignal.timeout(3000),
    });
    const data = await res.json();
    if (res.ok) {
      refreshMissions();
      loadDetail(currentMissionId);
    } else {
      alert(data.message ?? '처리 실패');
    }
  } catch(e) {
    alert('요청 실패: ' + e.message);
  }
}

// ── 액션 입력 모드 전환 ───────────────────────────────────
function switchActionMode(mode) {
  const isJs = mode === 'js';
  document.getElementById('mManualArea').style.display = isJs ? 'none' : '';
  document.getElementById('mJsArea').style.display     = isJs ? ''     : 'none';
  document.getElementById('mModeManualBtn').style.cssText =
    'flex:1;padding:5px 0;font-size:11px;font-weight:600;border:none;cursor:pointer;' +
    (isJs ? 'background:#f3f4f6;color:#6b7280' : 'background:#1d4ed8;color:#fff');
  document.getElementById('mModeJsBtn').style.cssText =
    'flex:1;padding:5px 0;font-size:11px;font-weight:600;border:none;cursor:pointer;' +
    (isJs ? 'background:#1d4ed8;color:#fff' : 'background:#f3f4f6;color:#6b7280');
}

function parseActionJs() {
  const raw   = document.getElementById('mJsInput').value.trim();
  const errEl = document.getElementById('mJsError');
  errEl.style.display = 'none';

  if (!raw) {
    errEl.textContent = '내용을 입력하세요.';
    errEl.style.display = '';
    return;
  }

  let parsed = null;

  // 1) 순수 JSON 배열 시도
  try { parsed = JSON.parse(raw); } catch {}

  // 2) JS 코드에서 배열 추출 시도 (const x = [...] 또는 [...] 단독)
  if (!Array.isArray(parsed)) {
    const m = raw.match(/(\[[\s\S]*\])/);
    if (m) {
      try { parsed = JSON.parse(m[1]); } catch {}
    }
  }

  if (!Array.isArray(parsed)) {
    errEl.textContent = '파싱 실패: JSON 배열 형식이 아닙니다.';
    errEl.style.display = '';
    return;
  }

  const result = [];
  for (let i = 0; i < parsed.length; i++) {
    const a = parsed[i];
    if (!a || typeof a.type !== 'string') {
      errEl.textContent = `[${i}] type 필드가 없습니다.`;
      errEl.style.display = '';
      return;
    }
    if (!ACTION_DEFS[a.type]) {
      errEl.textContent = `[${i}] 알 수 없는 type: "${a.type}"`;
      errEl.style.display = '';
      return;
    }
    result.push({ type: a.type, value: a.value ?? ACTION_DEFS[a.type].def ?? null });
  }

  if (!result.length) {
    errEl.textContent = '액션이 1개 이상 있어야 합니다.';
    errEl.style.display = '';
    return;
  }

  mActions = result;
  switchActionMode('manual');
  renderActions();
}

function init_missions() {
  const sel = document.getElementById('missionDroneFilter');
  if (sel) {
    DRONE_IDS.forEach(id => {
      const o = document.createElement('option');
      o.value = id; o.textContent = id;
      sel.appendChild(o);
    });
  }
  refreshMissions();
  setInterval(refreshMissions, 10000);
}
