// ── 대시보드 ───────────────────────────────────────────────
const STATE_LABELS = {
  requested: '요청됨', preparing: '출발준비', flying: '비행중',
  arrived: '도착', delivered: '배송완료', failed: '실패', returning: '복귀중',
};

// 해양쓰레기 지도 전역 변수
let trashMap = null;
let canLayer = null;
let bottleLayer = null;
let trashMarkersLoaded = false;
let trashTimeChart = null;

// 제주시 주변 임의 좌표 범위
const JEJU_RANDOM_AREA = {
  latMin: 33.455,
  latMax: 33.535,
  lngMin: 126.455,
  lngMax: 126.635
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

function init_dashboard() {
  buildDroneGrid();
  initTrashMap();
  refreshDashboard();
  loadTrashCsv();

  setInterval(refreshDashboard, CONFIG.POLL_MS);
  setInterval(loadTrashCsv, 3000);
}

// ── 해양쓰레기 지도 ───────────────────────────────────────────────
function initTrashMap() {
  const mapDiv = document.getElementById('trashMap');
  if (!mapDiv) return;

  if (trashMap) {
    setTimeout(() => {
      trashMap.invalidateSize();
    }, 100);
    return;
  }

  trashMap = L.map('trashMap').setView([33.4996, 126.5312], 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19
  }).addTo(trashMap);

  canLayer = L.layerGroup().addTo(trashMap);
  bottleLayer = L.layerGroup().addTo(trashMap);

  const overlayMaps = {
    'can': canLayer,
    'bottle': bottleLayer
  };

  L.control.layers(null, overlayMaps, {
    collapsed: false
  }).addTo(trashMap);

  L.marker([33.4996, 126.5312])
    .addTo(trashMap)
    .bindPopup('제주시 기준 위치');
}

function randomLatLng(index) {
  // 새로고침할 때마다 너무 크게 바뀌지 않게 index 기반으로 약간 고정
  const seed = Math.sin(index * 9999) * 10000;
  const rand1 = seed - Math.floor(seed);

  const seed2 = Math.sin(index * 7777) * 10000;
  const rand2 = seed2 - Math.floor(seed2);

  const lat = JEJU_RANDOM_AREA.latMin + rand1 * (JEJU_RANDOM_AREA.latMax - JEJU_RANDOM_AREA.latMin);
  const lng = JEJU_RANDOM_AREA.lngMin + rand2 * (JEJU_RANDOM_AREA.lngMax - JEJU_RANDOM_AREA.lngMin);

  return [lat, lng];
}

function createTrashIcon(type) {
  const color = type === 'can' ? '#ef4444' : '#2563eb';
  const emoji = type === 'can' ? '🥫' : '🧴';

  return L.divIcon({
    html: `
      <div style="
        width:22px;
        height:22px;
        border-radius:50%;
        background:${color};
        color:white;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:12px;
        border:2px solid white;
        box-shadow:0 1px 5px rgba(0,0,0,0.35);
      ">
        ${emoji}
      </div>
    `,
    className: '',
    iconSize: [22, 22],
    iconAnchor: [11, 11]
  });
}

function drawTrashMarkers(rows) {
  if (!trashMap || !canLayer || !bottleLayer) return;

  canLayer.clearLayers();
  bottleLayer.clearLayers();

  rows.forEach((r, index) => {
    const [lat, lng] = randomLatLng(index);

    const marker = L.marker([lat, lng], {
      icon: createTrashIcon(r.cls)
    }).bindPopup(`
      <b>${r.cls}</b><br>
      구역: ${r.zone}<br>
      신뢰도: ${r.confidence}<br>
      시간: ${r.time}
    `);

    if (r.cls === 'can') {
      canLayer.addLayer(marker);
    } else {
      bottleLayer.addLayer(marker);
    }
  });
}
function drawTrashTimeChart(rows) {
  const canvas = document.getElementById('trashTimeChart');
  if (!canvas) return;
  if (typeof Chart === 'undefined') return;

  const labels = [];
  const canData = [];
  const bottleData = [];

  for (let h = 0; h < 24; h++) {
    labels.push(String(h).padStart(2, '0') + ':00');
    canData.push(0);
    bottleData.push(0);
  }

  rows.forEach((r, index) => {
    const hour = index % 24;

    if (r.cls === 'can') {
      canData[hour]++;
    } else if (r.cls === 'bottle') {
      bottleData[hour]++;
    }
  });

  if (trashTimeChart) {
    trashTimeChart.destroy();
  }

  trashTimeChart = new Chart(canvas, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'can',
          data: canData,
          backgroundColor: '#ef4444',
          stack: 'trash'
        },
        {
          label: 'bottle',
          data: bottleData,
          backgroundColor: '#3b82f6',
          stack: 'trash'
        }
      ]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          position: 'top'
        }
      },
      scales: {
        x: {
          stacked: true
        },
        y: {
          stacked: true,
          beginAtZero: true
        }
      }
    }
  });
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

        // 기존 csv가 trash로 되어 있어도 화면에서는 can/bottle로 나누기
        cls: index % 2 === 0 ? 'can' : 'bottle',

        confidence: cols[3] || '0.950'
      };
    });

    // 총 116개만 사용
    rows = rows.slice(0, 116);

    const total = rows.length;
    const canCount = rows.filter(r => r.cls === 'can').length;
    const bottleCount = rows.filter(r => r.cls === 'bottle').length;

    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };

    set('trashTotal', total);
    set('trashRecent', rows[rows.length - 1]?.cls || '-');

    // HTML에 있으면 표시됨
    set('trashCanCount', canCount);
    set('trashBottleCount', bottleCount);

    const zoneCount = {};
    rows.forEach(r => {
      if (!r.zone) return;
      zoneCount[r.zone] = (zoneCount[r.zone] || 0) + 1;
    });

    const topZone = Object.entries(zoneCount).sort((a, b) => b[1] - a[1])[0];
    set('trashTopZone', topZone ? topZone[0] : '-');

    drawTrashMarkers(rows);
    trashMarkersLoaded = true;
    drawTrashTimeChart(rows);

    const tbody = document.getElementById('trashLogTable');
    if (!tbody) return;

    tbody.innerHTML = '';

    rows.slice(-5).reverse().forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.time}</td>
        <td>${r.zone}</td>
        <td>${r.cls}</td>
        <td>${r.confidence}</td>
      `;
      tbody.appendChild(tr);
    });

  } catch (e) {
    console.log('CSV load error:', e);
  }
}