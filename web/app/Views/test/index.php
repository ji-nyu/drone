<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>Stream Test</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0f172a; color: #e2e8f0; font-family: monospace; padding: 24px; }
  h1 { font-size: 18px; margin-bottom: 20px; color: #94a3b8; }
  .toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
  .toolbar input {
    background: #1e293b; border: 1px solid #334155; color: #e2e8f0;
    padding: 6px 12px; border-radius: 6px; font-family: monospace; font-size: 14px; width: 180px;
  }
  .btn {
    background: #334155; border: none; color: #e2e8f0; padding: 6px 14px;
    border-radius: 6px; cursor: pointer; font-family: monospace; font-size: 13px;
  }
  .btn:hover { background: #475569; }
  .btn.primary { background: #2563eb; }
  .btn.primary:hover { background: #1d4ed8; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  .panel { background: #1e293b; border-radius: 10px; overflow: hidden; }
  .panel-header {
    padding: 10px 16px; background: #0f172a;
    font-size: 12px; color: #64748b; display: flex; align-items: center; justify-content: space-between;
  }
  .panel-header span { color: #94a3b8; font-weight: bold; font-size: 13px; }
  .panel-body { position: relative; background: #000; aspect-ratio: 4/3; }
  .panel-body img { width: 100%; height: 100%; object-fit: contain; display: block; }
  .placeholder {
    position: absolute; inset: 0; display: flex; align-items: center;
    justify-content: center; color: #334155; font-size: 13px; flex-direction: column; gap: 8px;
  }
  .badge {
    display: inline-block; padding: 2px 8px; border-radius: 4px;
    font-size: 11px; background: #1e3a5f; color: #60a5fa;
  }
  .badge.live { background: #14532d; color: #4ade80; }
  .status { font-size: 12px; color: #64748b; padding: 8px 16px; min-height: 28px; }
</style>
</head>
<body>

<h1>Stream / Snapshot Test</h1>

<div class="toolbar">
  <input id="droneId" value="TT-01" placeholder="drone_id">
  <button class="btn primary" onclick="loadAll()">▶ 적용</button>
</div>

<div class="grid">

  <!-- Snapshot -->
  <div class="panel">
    <div class="panel-header">
      <span>Snapshot</span>
      <div style="display:flex;gap:8px;align-items:center">
        <span class="badge" id="snapBadge">JPEG</span>
        <button class="btn" onclick="refreshSnap()">새로고침</button>
        <button class="btn" id="snapAutoBtn" onclick="toggleAutoSnap()">자동 OFF</button>
      </div>
    </div>
    <div class="panel-body">
      <img id="snapImg" src="" alt="" style="display:none">
      <div class="placeholder" id="snapPh">
        <div>●</div><div>적용 버튼을 눌러 스냅샷 로드</div>
      </div>
    </div>
    <div class="status" id="snapStatus">—</div>
  </div>

  <!-- MJPEG Stream -->
  <div class="panel">
    <div class="panel-header">
      <span>MJPEG Stream</span>
      <div style="display:flex;gap:8px;align-items:center">
        <span class="badge live" id="streamBadge" style="display:none">● LIVE</span>
        <button class="btn primary" id="streamBtn" onclick="toggleStream()">▶ 시작</button>
      </div>
    </div>
    <div class="panel-body">
      <img id="streamImg" src="" alt="" style="display:none"
           onload="onStreamLoad()" onerror="onStreamErr()">
      <div class="placeholder" id="streamPh">
        <div>●</div><div>시작 버튼으로 스트림 연결</div>
      </div>
    </div>
    <div class="status" id="streamStatus">—</div>
  </div>

</div>

<script>
let _snapTimer  = null;
let _streamOn   = false;

function droneId() {
  return document.getElementById('droneId').value.trim() || 'TT-01';
}

// ── Snapshot ──────────────────────────────────────────
function refreshSnap() {
  const img  = document.getElementById('snapImg');
  const ph   = document.getElementById('snapPh');
  const stat = document.getElementById('snapStatus');
  const ts   = Date.now();
  const src  = `/test/snapshot?drone_id=${encodeURIComponent(droneId())}&_=${ts}`;

  stat.textContent = '로드 중...';
  const tmp = new Image();
  tmp.onload = () => {
    img.src = src;
    img.style.display = 'block';
    ph.style.display  = 'none';
    stat.textContent  = `OK · ${new Date().toLocaleTimeString('ko-KR')}`;
  };
  tmp.onerror = () => {
    img.style.display = 'none';
    ph.style.display  = 'flex';
    stat.textContent  = `503 no frame · ${new Date().toLocaleTimeString('ko-KR')}`;
  };
  tmp.src = src;
}

function toggleAutoSnap() {
  const btn = document.getElementById('snapAutoBtn');
  if (_snapTimer) {
    clearInterval(_snapTimer);
    _snapTimer = null;
    btn.textContent = '자동 OFF';
  } else {
    refreshSnap();
    _snapTimer = setInterval(refreshSnap, 1000);
    btn.textContent = '자동 ON';
    btn.style.background = '#15803d';
  }
}

// ── MJPEG Stream ──────────────────────────────────────
function toggleStream() {
  if (_streamOn) stopStream(); else startStream();
}

function startStream() {
  const img    = document.getElementById('streamImg');
  const ph     = document.getElementById('streamPh');
  const btn    = document.getElementById('streamBtn');
  const badge  = document.getElementById('streamBadge');
  const stat   = document.getElementById('streamStatus');

  img.src = `/test/stream?drone_id=${encodeURIComponent(droneId())}`;
  img.style.display = 'block';
  ph.style.display  = 'none';
  btn.textContent   = '■ 중지';
  btn.style.background = '#dc2626';
  badge.style.display  = 'inline-block';
  stat.textContent  = '스트림 연결 중...';
  _streamOn = true;
}

function stopStream() {
  const img   = document.getElementById('streamImg');
  const ph    = document.getElementById('streamPh');
  const btn   = document.getElementById('streamBtn');
  const badge = document.getElementById('streamBadge');
  const stat  = document.getElementById('streamStatus');

  img.src = '';
  img.style.display = 'none';
  ph.style.display  = 'flex';
  btn.textContent   = '▶ 시작';
  btn.style.background = '';
  badge.style.display  = 'none';
  stat.textContent  = '중지됨';
  _streamOn = false;
}

function onStreamLoad() {
  document.getElementById('streamStatus').textContent =
    `연결됨 · ${new Date().toLocaleTimeString('ko-KR')}`;
}
function onStreamErr() {
  document.getElementById('streamStatus').textContent = '스트림 오류 / no frame';
  stopStream();
}

function loadAll() {
  refreshSnap();
  if (_streamOn) { stopStream(); setTimeout(startStream, 200); }
}
</script>
</body>
</html>
