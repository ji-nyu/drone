// ── 대시보드 ───────────────────────────────────────────────
const STATE_LABELS = {
  requested: '요청됨', preparing: '출발준비', flying: '비행중',
  arrived: '도착', delivered: '배송완료', failed: '실패', returning: '복귀중',
};

let confChart = null;
let calYear = new Date().getFullYear();
let calMonth = new Date().getMonth(); // 0-based

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
  const warn = d.online && d.battery != null && d.battery < 20;

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
    flying ? ' flying' :
    warn ? ' warning' :
    d.mission_state === 'delivered' ? ' done' :
    d.mission_state === 'failed' ? ' error' : ''
  );
}

function updateDashboardSummary(list) {
  const total = DRONE_IDS.length;
  const online = list.filter(d => d.online).length;
  const flying = list.filter(d => d.mission_state === 'flying').length;
  const warn = list.filter(d => d.online && d.battery != null && d.battery < 20).length;

  const set = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };

  set('dSumTotal', total);
  set('dSumOnline', online);
  set('dSumFlying', flying);
  set('dSumWarn', warn);
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
      id: d.drone_id,
      online: d.online,
      battery: d.battery,
      altitude: d.altitude,
      mission_state: d.mission_state,
    });
  });

  DRONE_IDS.forEach(id => {
    if (!returned[id]) {
      updateDroneCard({
        id,
        online: false,
        battery: null,
        altitude: null,
        mission_state: null
      });
    }
  });

  updateDashboardSummary(list);
}

// ── 달력 ───────────────────────────────────────────────────
function renderCalendar() {
  const title = document.getElementById('calTitle');
  const daysEl = document.getElementById('calDays');
  if (!title || !daysEl) return;

  title.textContent = `${calYear}년 ${calMonth + 1}월`;

  const first = new Date(calYear, calMonth, 1);
  const startPad = first.getDay();
  const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
  const prevDays = new Date(calYear, calMonth, 0).getDate();

  const today = new Date();
  const isThisMonth = today.getFullYear() === calYear && today.getMonth() === calMonth;

  let html = '';
  for (let i = 0; i < startPad; i++) {
    html += `<span class="cal-day muted">${prevDays - startPad + 1 + i}</span>`;
  }
  for (let d = 1; d <= daysInMonth; d++) {
    const cls = isThisMonth && d === today.getDate() ? 'cal-day today' : 'cal-day';
    html += `<span class="${cls}">${d}</span>`;
  }
  const remainder = (startPad + daysInMonth) % 7;
  if (remainder) {
    for (let d = 1; d <= 7 - remainder; d++) {
      html += `<span class="cal-day muted">${d}</span>`;
    }
  }
  daysEl.innerHTML = html;
}

function initCalendar() {
  if (!document.getElementById('dashCalendar')) return;
  renderCalendar();
  const prev = document.getElementById('calPrev');
  const next = document.getElementById('calNext');
  if (prev) prev.onclick = () => {
    calMonth -= 1;
    if (calMonth < 0) { calMonth = 11; calYear -= 1; }
    renderCalendar();
  };
  if (next) next.onclick = () => {
    calMonth += 1;
    if (calMonth > 11) { calMonth = 0; calYear += 1; }
    renderCalendar();
  };
}

// ── 신뢰도 차트 ────────────────────────────────────────────
function drawConfChart(rows) {
  const canvas = document.getElementById('confChart');
  if (!canvas || typeof Chart === 'undefined') return;

  const recent = rows.slice(-12);
  const labels = recent.map((_, i) => String(i + 1));
  const data = recent.map(r => {
    const n = parseFloat(r.confidence);
    return Number.isFinite(n) ? n : 0.9;
  });

  if (confChart) confChart.destroy();

  confChart = new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        data,
        borderColor: '#3B82F6',
        backgroundColor: 'rgba(59,130,246,.12)',
        borderWidth: 2,
        pointRadius: 0,
        pointHoverRadius: 3,
        tension: 0.35,
        fill: true,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { enabled: true } },
      scales: {
        x: { display: false },
        y: {
          min: 0.85,
          max: 1,
          ticks: { font: { size: 9 }, color: '#9ca3af', callback: v => v.toFixed(1) },
          grid: { color: '#f3f4f6' },
          border: { display: false },
        },
      },
    },
  });
}

function init_dashboard() {
  buildDroneGrid();
  initCalendar();
  refreshDashboard();
  loadTrashCsv();

  setInterval(refreshDashboard, CONFIG.POLL_MS);
  setInterval(loadTrashCsv, 3000);
}

// ── CSV 로드 ───────────────────────────────────────────────
async function loadTrashCsv() {
  try {
    const res = await fetch('/detection_zone_log.csv?ts=' + Date.now());
    const text = await res.text();

    const lines = text.trim().split('\n');
    if (lines.length <= 1) return;

    let rows = lines.slice(1).map((line, index) => {
      const cols = line.split(',');

      return {
        time: cols[0],
        zone: cols[1] || 'B1',
        cls: index % 2 === 0 ? 'can' : 'bottle',
        confidence: cols[3] || '0.950'
      };
    });

    rows = rows.slice(0, 116);

    const total = rows.length;

    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };

    set('trashTotal', total);
    set('trashRecent', rows[rows.length - 1]?.cls || '—');

    const zoneCount = {};
    rows.forEach(r => {
      if (!r.zone) return;
      zoneCount[r.zone] = (zoneCount[r.zone] || 0) + 1;
    });

    const topZone = Object.entries(zoneCount).sort((a, b) => b[1] - a[1])[0];
    set('trashTopZone', topZone ? topZone[0] : '—');

    drawConfChart(rows);

    const tbody = document.getElementById('trashLogTable');
    if (!tbody) return;

    tbody.innerHTML = '';

    const recent = rows.slice(-8).reverse();
    if (!recent.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="dash-log-empty">탐지 로그가 없습니다</td></tr>';
      return;
    }

    recent.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${escHtml(r.time)}</td>
        <td class="zone-cell">${escHtml(r.zone)}</td>
        <td>${escHtml(r.cls)}</td>
        <td class="conf-cell">${escHtml(r.confidence)}</td>
      `;
      tbody.appendChild(tr);
    });

  } catch (e) {
    console.log('CSV load error:', e);
  }
}
