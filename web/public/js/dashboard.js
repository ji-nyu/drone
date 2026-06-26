// ── 대시보드 ───────────────────────────────────────────────
const STATE_LABELS = {
  requested: '요청됨', preparing: '출발준비', flying: '비행중',
  arrived: '도착', delivered: '배송완료', failed: '실패', returning: '복귀중',
};

function buildDroneGrid() {
  const grid = document.getElementById('droneGrid');
  if (!grid) return;
  DRONE_IDS.forEach(id => {
    const card = document.createElement('div');
    card.className = 'drone-card offline';
    card.id = 'dc-' + id;
    card.onclick = () => { location.href = '/drone/control?drone=' + id; };
    card.innerHTML =
      `<div class="drone-card-header">` +
        `<span class="drone-id">${id}</span>` +
        `<span class="drone-status-dot offline" id="dd-${id}"></span>` +
      `</div>` +
      `<div class="drone-battery-row">` +
        `<div class="drone-battery-track">` +
          `<div class="drone-battery-fill" id="dbf-${id}" style="width:0%;background:#d1d5db"></div>` +
        `</div>` +
        `<span class="drone-battery-pct" id="dbp-${id}">--%</span>` +
      `</div>` +
      `<div class="drone-state-label">` +
        `<span id="dalt-${id}">-- m</span>` +
        `<span class="drone-state-badge" id="dst-${id}">오프라인</span>` +
      `</div>`;
    grid.appendChild(card);
  });
}

function updateDroneCard(d) {
  const card = document.getElementById('dc-' + d.id);
  if (!card) return;

  const flying = d.mission_state === 'flying';
  const warn   = d.online && d.battery != null && d.battery < 20;

  card.classList.toggle('offline', !d.online);

  document.getElementById('dd-' + d.id).className =
    'drone-status-dot ' + (!d.online ? 'offline' : flying ? 'flying' : warn ? 'warning' : 'online');

  if (d.battery != null) {
    const color = d.battery > 50 ? '#22c55e' : d.battery > 20 ? '#f59e0b' : '#ef4444';
    document.getElementById('dbf-' + d.id).style.cssText = `width:${d.battery}%;background:${color}`;
    document.getElementById('dbp-' + d.id).textContent = d.battery + '%';
  }
  if (d.altitude != null) {
    document.getElementById('dalt-' + d.id).textContent = d.altitude + ' m';
  }

  const stEl = document.getElementById('dst-' + d.id);
  stEl.textContent = !d.online ? '오프라인' : (STATE_LABELS[d.mission_state] ?? '대기중');
  stEl.className = 'drone-state-badge' + (
    !d.online ? '' :
    flying                        ? ' flying'  :
    warn                          ? ' warning' :
    d.mission_state === 'delivered' ? ' done'  :
    d.mission_state === 'failed'    ? ' error' : ''
  );
}

function updateDashboardSummary(list) {
  const total   = DRONE_IDS.length;
  const online  = list.filter(d => d.online).length;
  const flying  = list.filter(d => d.mission_state === 'flying').length;
  const warn    = list.filter(d => d.online && d.battery != null && d.battery < 20).length;
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  set('dSumTotal',  total);
  set('dSumOnline', online);
  set('dSumFlying', flying);
  set('dSumWarn',   warn);
}

async function refreshDashboard() {
  const list = await fetchDroneSummary();
  if (!list) {
    DRONE_IDS.forEach(id => updateDroneCard({
      id, online: false, battery: null, altitude: null, mission_state: null,
    }));
    updateDashboardSummary([]);
    return;
  }
  const returned = {};
  list.forEach(d => {
    returned[d.drone_id] = true;
    updateDroneCard({
      id: d.drone_id, online: d.online,
      battery: d.battery, altitude: d.altitude, mission_state: d.mission_state,
    });
  });
  DRONE_IDS.forEach(id => {
    if (!returned[id]) {
      updateDroneCard({ id, online: false, battery: null, altitude: null, mission_state: null });
    }
  });
  updateDashboardSummary(list);
}

function init_dashboard() {
  buildDroneGrid();
  refreshDashboard();
  setInterval(refreshDashboard, CONFIG.POLL_MS);
}
