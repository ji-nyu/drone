<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>드론택배 관제 시스템</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
  font-family: 'Segoe UI', 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
  font-size: 13px;
  background: #f4f5f7;
  color: #1a1d23;
  min-height: 100vh;
  display: flex;
  overflow: hidden;
}

/* ─── Sidebar ─────────────────────────────────────── */
.sidebar {
  width: 220px;
  min-width: 220px;
  background: #fff;
  border-right: 1px solid #e8eaed;
  display: flex;
  flex-direction: column;
  height: 100vh;
  position: sticky;
  top: 0;
}
.sidebar-logo {
  padding: 18px 20px 16px;
  border-bottom: 1px solid #f0f1f3;
  display: flex;
  align-items: center;
  gap: 10px;
}
.sidebar-logo-icon {
  width: 32px; height: 32px;
  background: #1a56db;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}
.sidebar-logo-text { line-height: 1.2; }
.sidebar-logo-title { font-size: 13px; font-weight: 700; color: #111827; }
.sidebar-logo-sub   { font-size: 10px; color: #9ca3af; margin-top: 1px; }

.sidebar-nav {
  flex: 1;
  padding: 10px 10px;
  overflow-y: auto;
}
.nav-section-label {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .7px;
  color: #c4c9d4;
  padding: 10px 10px 6px;
}
.nav-item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 8px 10px;
  border-radius: 7px;
  cursor: pointer;
  color: #6b7280;
  font-weight: 500;
  font-size: 13px;
  transition: background .15s, color .15s;
  user-select: none;
  margin-bottom: 1px;
}
.nav-item:hover { background: #f9fafb; color: #374151; }
.nav-item.active { background: #eff6ff; color: #1d4ed8; }
.nav-item.active .nav-icon { color: #1d4ed8; }
.nav-icon { font-size: 15px; width: 18px; text-align: center; flex-shrink: 0; }
.nav-badge {
  margin-left: auto;
  background: #fee2e2;
  color: #dc2626;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
}
.nav-badge.blue { background: #dbeafe; color: #1d4ed8; }
.nav-badge.green { background: #dcfce7; color: #16a34a; }

.sidebar-footer {
  border-top: 1px solid #f0f1f3;
  padding: 14px 16px;
}
.sidebar-conn {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 8px;
}
.conn-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: #d1d5db;
  flex-shrink: 0;
  transition: background .3s;
}
.conn-dot.on { background: #22c55e; animation: blink 2s ease-in-out infinite; }
.conn-dot.warn { background: #f59e0b; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.4} }
.drone-summary {
  font-size: 11px;
  color: #9ca3af;
  display: flex;
  gap: 10px;
}
.drone-summary span { display: flex; align-items: center; gap: 4px; }
.dot-sm {
  width: 5px; height: 5px;
  border-radius: 50%;
  display: inline-block;
}

/* ─── Main ────────────────────────────────────────── */
.main-wrap {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  height: 100vh;
  overflow: hidden;
}

.topbar {
  height: 52px;
  background: #fff;
  border-bottom: 1px solid #e8eaed;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  flex-shrink: 0;
}
.topbar-left {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}
.topbar-left .page-icon { font-size: 16px; }
.topbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}
.clock {
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  font-variant-numeric: tabular-nums;
}

.page {
  flex: 1;
  overflow: auto;
  display: none;
  padding: 20px;
}
.page.active { display: block; }
.page.flex-page { display: none; }
.page.flex-page.active { display: flex; gap: 16px; overflow: hidden; padding: 0; }

/* ─── 대시보드 ────────────────────────────────────── */
.summary-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}
.summary-card {
  background: #fff;
  border: 1px solid #e8eaed;
  border-radius: 10px;
  padding: 16px 18px;
}
.summary-card-label {
  font-size: 11px;
  color: #9ca3af;
  margin-bottom: 6px;
  font-weight: 500;
}
.summary-card-value {
  font-size: 28px;
  font-weight: 700;
  color: #111827;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}
.summary-card-value.blue  { color: #1d4ed8; }
.summary-card-value.green { color: #16a34a; }
.summary-card-value.red   { color: #dc2626; }
.summary-card-sub {
  font-size: 11px;
  color: #9ca3af;
  margin-top: 4px;
}

.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 12px;
}

.drone-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 10px;
}
.drone-card {
  background: #fff;
  border: 1px solid #e8eaed;
  border-radius: 10px;
  padding: 14px 16px;
  cursor: pointer;
  transition: border-color .15s, box-shadow .15s;
  position: relative;
}
.drone-card:hover { border-color: #93c5fd; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.drone-card.selected { border-color: #1d4ed8; box-shadow: 0 0 0 3px #dbeafe; }
.drone-card.offline  { opacity: .6; }
.drone-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}
.drone-id {
  font-size: 12px;
  font-weight: 700;
  color: #111827;
}
.drone-status-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.drone-status-dot.online  { background: #22c55e; box-shadow: 0 0 0 2px #dcfce7; }
.drone-status-dot.flying  { background: #3b82f6; box-shadow: 0 0 0 2px #dbeafe; animation: blink 1.5s ease-in-out infinite; }
.drone-status-dot.warning { background: #f59e0b; box-shadow: 0 0 0 2px #fef3c7; }
.drone-status-dot.offline { background: #d1d5db; }
.drone-battery-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}
.drone-battery-track {
  flex: 1;
  height: 4px;
  background: #f3f4f6;
  border-radius: 4px;
  overflow: hidden;
}
.drone-battery-fill { height: 100%; border-radius: 4px; transition: width .5s; }
.drone-battery-pct {
  font-size: 11px;
  font-weight: 600;
  color: #374151;
  width: 28px;
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.drone-state-label {
  font-size: 11px;
  color: #6b7280;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.drone-state-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 4px;
  background: #f3f4f6;
  color: #374151;
}
.drone-state-badge.flying  { background: #dbeafe; color: #1d4ed8; }
.drone-state-badge.warning { background: #fef3c7; color: #92400e; }
.drone-state-badge.done    { background: #dcfce7; color: #166534; }
.drone-state-badge.error   { background: #fee2e2; color: #991b1b; }

/* ─── 드론 관제 (상세) ────────────────────────────── */
.control-page-left {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
  border-right: 1px solid #e8eaed;
  background: #fff;
}
.control-page-right {
  width: 300px;
  min-width: 300px;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  background: #fff;
}
.control-topbar {
  height: 48px;
  border-bottom: 1px solid #f0f1f3;
  display: flex;
  align-items: center;
  padding: 0 16px;
  gap: 10px;
  flex-shrink: 0;
}
.drone-select {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 600;
  color: #111827;
  cursor: pointer;
  outline: none;
  appearance: none;
  padding-right: 24px;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%239ca3af'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 8px center;
}
.drone-select:focus { border-color: #93c5fd; }
.control-stat-bar {
  display: flex;
  border-bottom: 1px solid #f0f1f3;
  flex-shrink: 0;
}
.ctrl-stat {
  flex: 1;
  padding: 12px 16px;
  border-right: 1px solid #f0f1f3;
}
.ctrl-stat:last-child { border-right: none; }
.ctrl-stat-label {
  font-size: 10px;
  color: #9ca3af;
  margin-bottom: 4px;
  font-weight: 500;
}
.ctrl-stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  font-variant-numeric: tabular-nums;
  line-height: 1;
}
.ctrl-stat-unit { font-size: 11px; font-weight: 400; color: #9ca3af; }
.ctrl-stat-sub  { font-size: 10px; color: #9ca3af; margin-top: 3px; }
.ctrl-battery-bar {
  height: 3px; background: #f3f4f6; border-radius: 2px; margin-top: 5px; overflow: hidden;
}
.ctrl-battery-fill { height: 100%; border-radius: 2px; transition: width .5s, background .4s; }

.video-area {
  flex: 1;
  background: #0d0f14;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 0;
  overflow: hidden;
}
.video-feed {
  width: 100%; height: 100%;
  object-fit: contain;
  display: none;
}
.video-placeholder {
  text-align: center;
  color: #4b5563;
}
.video-placeholder-icon { font-size: 40px; opacity: .35; margin-bottom: 8px; }
.video-placeholder p    { font-size: 13px; }
.video-placeholder small { font-size: 11px; color: #374151; margin-top: 4px; display: block; }
.live-badge {
  position: absolute; top: 10px; right: 10px;
  background: #dc2626; color: #fff;
  font-size: 10px; font-weight: 700;
  padding: 3px 8px; border-radius: 4px;
  letter-spacing: .4px; display: none;
}
.video-coords {
  position: absolute; bottom: 10px; left: 12px;
  font-size: 10px; color: rgba(255,255,255,.45);
  font-variant-numeric: tabular-nums;
}

.log-area {
  height: 150px;
  border-top: 1px solid #f0f1f3;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}
.area-header {
  height: 36px;
  border-bottom: 1px solid #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 14px;
  flex-shrink: 0;
}
.area-title {
  font-size: 11px;
  font-weight: 600;
  color: #374151;
}
.btn-xs {
  font-size: 11px;
  background: none;
  border: 1px solid #e5e7eb;
  color: #9ca3af;
  padding: 2px 8px;
  border-radius: 4px;
  cursor: pointer;
  transition: all .15s;
}
.btn-xs:hover { border-color: #93c5fd; color: #1d4ed8; }
.log-body {
  flex: 1;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: #e5e7eb transparent;
}
.log-entry {
  display: flex;
  align-items: baseline;
  gap: 8px;
  padding: 3px 14px;
  font-size: 11px;
  border-bottom: 1px solid #fafafa;
}
.log-time { color: #d1d5db; font-variant-numeric: tabular-nums; flex-shrink: 0; width: 58px; }
.log-lv {
  font-size: 10px; font-weight: 600;
  padding: 1px 5px; border-radius: 3px; flex-shrink: 0;
}
.log-lv.info  { background: #eff6ff; color: #1d4ed8; }
.log-lv.warn  { background: #fffbeb; color: #b45309; }
.log-lv.error { background: #fef2f2; color: #dc2626; }
.log-lv.ok    { background: #f0fdf4; color: #15803d; }
.log-msg { color: #374151; }

/* Right panel */
.rpanel-section {
  border-bottom: 1px solid #f0f1f3;
  padding: 14px 16px;
}
.rpanel-title {
  font-size: 11px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 12px;
}

/* State machine */
.state-list { display: flex; flex-direction: column; }
.state-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  position: relative;
}
.state-connector {
  position: absolute;
  left: 11px; top: 24px;
  width: 1px;
  height: calc(100% - 4px);
  background: #e5e7eb;
  z-index: 0;
}
.state-row.done .state-connector,
.state-row.active .state-connector { background: #93c5fd; }
.state-node {
  width: 24px; height: 24px;
  border-radius: 50%;
  border: 1.5px solid #e5e7eb;
  background: #f9fafb;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px;
  flex-shrink: 0;
  z-index: 1; position: relative;
  margin-top: 8px;
  transition: all .2s;
}
.state-row.done   .state-node { border-color: #93c5fd; background: #eff6ff; }
.state-row.active .state-node { border-color: #1d4ed8; background: #dbeafe; box-shadow: 0 0 0 3px #bfdbfe; }
.state-row.fail   .state-node { border-color: #fca5a5; background: #fef2f2; }
.state-body { padding: 6px 0 10px; flex: 1; }
.state-name {
  font-size: 12px;
  font-weight: 500;
  color: #9ca3af;
  transition: color .2s;
}
.state-row.done   .state-name { color: #1d4ed8; }
.state-row.active .state-name { color: #111827; font-weight: 600; }
.state-row.fail   .state-name { color: #dc2626; }
.state-desc { font-size: 10px; color: #d1d5db; margin-top: 1px; }
.state-ts   { font-size: 10px; color: #93c5fd; margin-top: 2px; display: none; }
.state-row.done .state-ts,
.state-row.active .state-ts { display: block; }

/* Control buttons */
.btn-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
}
.btn {
  padding: 9px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #374151;
  border-radius: 7px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 500;
  display: flex; align-items: center; justify-content: center; gap: 5px;
  transition: all .15s;
}
.btn:hover   { background: #f9fafb; border-color: #d1d5db; }
.btn:active  { transform: scale(.97); }
.btn.primary { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
.btn.primary:hover { background: #1e40af; }
.btn.danger  { border-color: #fca5a5; color: #dc2626; background: #fff; }
.btn.danger:hover { background: #fef2f2; }
.btn.full    { grid-column: 1/-1; }
.btn:disabled { opacity: .35; cursor: not-allowed; pointer-events: none; }

/* Info grid */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
}
.info-cell {
  background: #f9fafb;
  border: 1px solid #f3f4f6;
  border-radius: 6px;
  padding: 8px 10px;
}
.info-cell.full { grid-column: 1/-1; }
.info-cell-label { font-size: 10px; color: #9ca3af; margin-bottom: 3px; text-transform: uppercase; letter-spacing: .3px; }
.info-cell-value { font-size: 13px; font-weight: 600; color: #111827; font-variant-numeric: tabular-nums; }
.info-cell-value.blue  { color: #1d4ed8; }
.info-cell-value.green { color: #15803d; }
.info-cell-value.muted { font-size: 11px; font-weight: 400; color: #6b7280; }

/* ─── 임무 현황 ───────────────────────────────────── */
.mission-table-wrap {
  background: #fff;
  border: 1px solid #e8eaed;
  border-radius: 10px;
  overflow: hidden;
}
.mission-table {
  width: 100%;
  border-collapse: collapse;
}
.mission-table th {
  background: #f9fafb;
  border-bottom: 1px solid #e8eaed;
  padding: 10px 14px;
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.mission-table td {
  padding: 11px 14px;
  border-bottom: 1px solid #f3f4f6;
  font-size: 12px;
  color: #374151;
}
.mission-table tr:last-child td { border-bottom: none; }
.mission-table tr:hover td { background: #fafafa; }
.badge {
  display: inline-block;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
}
.badge.blue    { background: #dbeafe; color: #1e40af; }
.badge.green   { background: #dcfce7; color: #166534; }
.badge.gray    { background: #f3f4f6; color: #374151; }
.badge.yellow  { background: #fef9c3; color: #854d0e; }
.badge.red     { background: #fee2e2; color: #991b1b; }

/* ─── 설정 ────────────────────────────────────────── */
.settings-card {
  background: #fff;
  border: 1px solid #e8eaed;
  border-radius: 10px;
  margin-bottom: 14px;
  overflow: hidden;
}
.settings-card-header {
  padding: 14px 18px;
  border-bottom: 1px solid #f3f4f6;
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}
.settings-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 18px;
  border-bottom: 1px solid #f9fafb;
}
.settings-row:last-child { border-bottom: none; }
.settings-label { font-size: 13px; color: #374151; }
.settings-desc  { font-size: 11px; color: #9ca3af; margin-top: 2px; }
.settings-input {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 6px 10px;
  font-size: 12px;
  color: #111827;
  outline: none;
  width: 200px;
}
.settings-input:focus { border-color: #93c5fd; }

/* Scrollbars */
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
::-webkit-scrollbar-track { background: transparent; }
</style>
</head>
<body>

<!-- ─── Sidebar ───────────────────────────────────────── -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon">🚁</div>
    <div class="sidebar-logo-text">
      <div class="sidebar-logo-title">DroneControl</div>
      <div class="sidebar-logo-sub">제주한라대 드론택배</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">메인</div>
    <div class="nav-item active" data-page="dashboard">
      <span class="nav-icon">▦</span> 대시보드
      <span class="nav-badge blue" id="navOnline">0</span>
    </div>
    <div class="nav-item" data-page="control">
      <span class="nav-icon">✦</span> 드론 관제
    </div>

    <div class="nav-section-label">운영</div>
    <div class="nav-item" data-page="missions">
      <span class="nav-icon">≡</span> 임무 현황
      <span class="nav-badge" id="navActive" style="display:none">0</span>
    </div>
    <div class="nav-item" data-page="logs">
      <span class="nav-icon">◈</span> 비행 로그
    </div>

    <div class="nav-section-label">시스템</div>
    <div class="nav-item" data-page="settings">
      <span class="nav-icon">⊙</span> 설정
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-conn">
      <div class="conn-dot" id="sideConnDot"></div>
      <span id="sideConnLabel">API 서버 미연결</span>
    </div>
    <div class="drone-summary">
      <span><span class="dot-sm" style="background:#22c55e"></span> <span id="sumOnline">0</span> 온라인</span>
      <span><span class="dot-sm" style="background:#3b82f6"></span> <span id="sumFlying">0</span> 비행중</span>
      <span><span class="dot-sm" style="background:#d1d5db"></span> <span id="sumOffline">0</span> 오프라인</span>
    </div>
  </div>
</aside>

<!-- ─── Main ──────────────────────────────────────────── -->
<div class="main-wrap">

  <div class="topbar">
    <div class="topbar-left">
      <span class="page-icon" id="topbarIcon">▦</span>
      <span id="topbarTitle">대시보드</span>
    </div>
    <div class="topbar-right">
      <span class="clock" id="clock">--:--:--</span>
    </div>
  </div>

  <!-- 대시보드 -->
  <div class="page active" id="page-dashboard">
    <div class="summary-row">
      <div class="summary-card">
        <div class="summary-card-label">전체 기체</div>
        <div class="summary-card-value" id="sumTotal">0</div>
        <div class="summary-card-sub">등록된 드론</div>
      </div>
      <div class="summary-card">
        <div class="summary-card-label">비행 중</div>
        <div class="summary-card-value blue" id="sumFlyingTop">0</div>
        <div class="summary-card-sub">현재 임무 수행</div>
      </div>
      <div class="summary-card">
        <div class="summary-card-label">온라인</div>
        <div class="summary-card-value green" id="sumOnlineTop">0</div>
        <div class="summary-card-sub">연결 정상</div>
      </div>
      <div class="summary-card">
        <div class="summary-card-label">경고</div>
        <div class="summary-card-value red" id="sumWarnTop">0</div>
        <div class="summary-card-sub">배터리 부족 포함</div>
      </div>
    </div>

    <div class="section-title">기체 현황</div>
    <div class="drone-grid" id="droneGrid"></div>
  </div>

  <!-- 드론 관제 -->
  <div class="page flex-page" id="page-control">

    <div class="control-page-left">
      <div class="control-topbar">
        <select class="drone-select" id="droneSelect" onchange="selectDrone(this.value)"></select>
        <span id="ctrlConnPill" style="font-size:11px;color:#9ca3af">—</span>
      </div>
      <div class="control-stat-bar">
        <div class="ctrl-stat">
          <div class="ctrl-stat-label">배터리</div>
          <div class="ctrl-stat-value" id="ctrlBattery">--<span class="ctrl-stat-unit">%</span></div>
          <div class="ctrl-battery-bar"><div class="ctrl-battery-fill" id="ctrlBatteryFill" style="width:0%"></div></div>
          <div class="ctrl-stat-sub" id="ctrlBatteryEst">— 분 추정</div>
        </div>
        <div class="ctrl-stat">
          <div class="ctrl-stat-label">고도</div>
          <div class="ctrl-stat-value" id="ctrlAltitude">--<span class="ctrl-stat-unit">m</span></div>
          <div class="ctrl-stat-sub" id="ctrlSpeed">속도 --</div>
        </div>
        <div class="ctrl-stat">
          <div class="ctrl-stat-label">온도</div>
          <div class="ctrl-stat-value" id="ctrlTemp">--<span class="ctrl-stat-unit">°C</span></div>
          <div class="ctrl-stat-sub" id="ctrlTempRange">— / —</div>
        </div>
        <div class="ctrl-stat">
          <div class="ctrl-stat-label">임무</div>
          <div class="ctrl-stat-value" id="ctrlMissionState" style="font-size:14px;padding-top:2px">대기중</div>
          <div class="ctrl-stat-sub" id="ctrlMissionId">ID: —</div>
        </div>
      </div>

      <div class="video-area">
        <img class="video-feed" id="videoFeed" src="" alt="드론 영상">
        <div class="video-placeholder" id="videoPlaceholder">
          <div class="video-placeholder-icon">📡</div>
          <p>영상 스트림 대기 중</p>
          <small>Python API 연결 후 자동으로 표시됩니다</small>
        </div>
        <div class="live-badge" id="videoBadge">● LIVE</div>
        <div class="video-coords" id="videoCoords"></div>
      </div>

      <div class="log-area">
        <div class="area-header">
          <span class="area-title">비행 로그</span>
          <button class="btn-xs" onclick="clearLogs()">지우기</button>
        </div>
        <div class="log-body" id="logBody"></div>
      </div>
    </div>

    <div class="control-page-right">
      <!-- 상태 머신 -->
      <div class="rpanel-section">
        <div class="rpanel-title">임무 진행 상태</div>
        <div class="state-list" id="stateChain"></div>
      </div>
      <!-- 제어 -->
      <div class="rpanel-section">
        <div class="rpanel-title">드론 제어</div>
        <div class="btn-grid">
          <button class="btn primary" id="btnTakeoff"   onclick="droneCmd('takeoff')"   disabled>↑ 이륙</button>
          <button class="btn"         id="btnLand"      onclick="droneCmd('land')"      disabled>↓ 착륙</button>
          <button class="btn"         id="btnReturn"    onclick="droneCmd('return')"    disabled>⟳ 귀환</button>
          <button class="btn"         id="btnHover"     onclick="droneCmd('hover')"     disabled>◎ 정지비행</button>
          <button class="btn danger full" id="btnEmergency" onclick="droneCmd('emergency')" disabled>⚠ 긴급정지</button>
        </div>
      </div>
      <!-- 기체 정보 -->
      <div class="rpanel-section">
        <div class="rpanel-title">기체 정보</div>
        <div class="info-grid">
          <div class="info-cell"><div class="info-cell-label">기체</div><div class="info-cell-value">Tello TT</div></div>
          <div class="info-cell"><div class="info-cell-label">SDK</div><div class="info-cell-value">3.0</div></div>
          <div class="info-cell"><div class="info-cell-label">비행시간</div><div class="info-cell-value blue" id="flightTime">--:--</div></div>
          <div class="info-cell"><div class="info-cell-label">최고 고도</div><div class="info-cell-value green" id="maxAlt">-- m</div></div>
          <div class="info-cell"><div class="info-cell-label">Yaw</div><div class="info-cell-value" id="yaw">--°</div></div>
          <div class="info-cell"><div class="info-cell-label">Pitch/Roll</div><div class="info-cell-value" id="pitchRoll">-- / --</div></div>
          <div class="info-cell full"><div class="info-cell-label">API 서버</div><div class="info-cell-value muted" id="apiEndpoint">—</div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- 임무 현황 -->
  <div class="page" id="page-missions">
    <div class="section-title">전체 임무 현황</div>
    <div class="mission-table-wrap">
      <table class="mission-table">
        <thead>
          <tr>
            <th>임무 ID</th>
            <th>기체</th>
            <th>출발지</th>
            <th>목적지</th>
            <th>상태</th>
            <th>시작 시간</th>
          </tr>
        </thead>
        <tbody id="missionTableBody">
          <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:30px">임무 데이터 없음</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 비행 로그 -->
  <div class="page" id="page-logs">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <div class="section-title" style="margin:0">전체 비행 로그</div>
      <button class="btn-xs" onclick="clearFullLogs()">전체 지우기</button>
    </div>
    <div style="background:#fff;border:1px solid #e8eaed;border-radius:10px;overflow:hidden">
      <div class="log-body" id="fullLogBody" style="height:calc(100vh - 160px)"></div>
    </div>
  </div>

  <!-- 설정 -->
  <div class="page" id="page-settings">
    <div class="section-title">시스템 설정</div>
    <div class="settings-card">
      <div class="settings-card-header">API 서버 연결</div>
      <div class="settings-row">
        <div>
          <div class="settings-label">Python API 서버 주소</div>
          <div class="settings-desc">OrangePi 드론 서버 IP (기본: 10.10.0.9:8000)</div>
        </div>
        <input class="settings-input" id="settingApiBase" value="http://10.10.0.9:8000">
      </div>
      <div class="settings-row">
        <div>
          <div class="settings-label">영상 스트림 경로</div>
          <div class="settings-desc">ffmpeg MJPEG 스트림 경로</div>
        </div>
        <input class="settings-input" id="settingStreamPath" value="/video/stream.mjpeg">
      </div>
      <div class="settings-row">
        <div>
          <div class="settings-label">폴링 주기</div>
          <div class="settings-desc">상태 조회 간격 (ms)</div>
        </div>
        <input class="settings-input" id="settingPollMs" value="1000" type="number">
      </div>
    </div>
    <div class="settings-card">
      <div class="settings-card-header">드론 등록</div>
      <div class="settings-row">
        <div>
          <div class="settings-label">등록 기체 수</div>
          <div class="settings-desc">최대 10대까지 등록 가능합니다</div>
        </div>
        <input class="settings-input" id="settingDroneCount" value="10" type="number" min="1" max="10">
      </div>
    </div>
    <button class="btn primary" style="margin-top:4px" onclick="applySettings()">설정 저장</button>
  </div>

</div><!-- /.main-wrap -->

<script>
// ─── Config ────────────────────────────────────────────────
const CONFIG = {
  API_BASE:    'http://10.10.0.9:8000',
  STREAM_PATH: '/video/stream.mjpeg',
  POLL_MS:     1000,
  DRONE_COUNT: 10,
};
document.getElementById('apiEndpoint').textContent = CONFIG.API_BASE;

// ─── Navigation ────────────────────────────────────────────
const PAGE_META = {
  dashboard: { icon: '▦',  title: '대시보드' },
  control:   { icon: '✦', title: '드론 관제' },
  missions:  { icon: '≡',  title: '임무 현황' },
  logs:      { icon: '◈',  title: '비행 로그' },
  settings:  { icon: '⊙',  title: '설정' },
};
let currentPage = 'dashboard';

document.querySelectorAll('.nav-item').forEach(el => {
  el.addEventListener('click', () => {
    const p = el.dataset.page;
    if (!p) return;
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('.page').forEach(pg => pg.classList.remove('active'));
    document.getElementById('page-' + p).classList.add('active');
    const meta = PAGE_META[p];
    document.getElementById('topbarIcon').textContent = meta.icon;
    document.getElementById('topbarTitle').textContent = meta.title;
    currentPage = p;
  });
});

// ─── Clock ─────────────────────────────────────────────────
function updateClock() {
  document.getElementById('clock').textContent = new Date().toTimeString().slice(0,8);
}
setInterval(updateClock, 1000);
updateClock();

// ─── Drone state ────────────────────────────────────────────
const DRONE_IDS = Array.from({length: CONFIG.DRONE_COUNT}, (_, i) => `TT-${String(i+1).padStart(2,'0')}`);

const droneData = {};
DRONE_IDS.forEach(id => {
  droneData[id] = {
    id, online: false, battery: null, altitude: null, speed: null,
    temp_low: null, temp_high: null, yaw: null, pitch: null, roll: null,
    lat: null, lon: null, mission_id: null, mission_state: null,
  };
});

let selectedDroneId = DRONE_IDS[0];

// ─── Build drone grid ──────────────────────────────────────
function buildDroneGrid() {
  const grid = document.getElementById('droneGrid');
  const sel  = document.getElementById('droneSelect');
  grid.innerHTML = '';
  sel.innerHTML  = '';
  DRONE_IDS.forEach(id => {
    // card
    const card = document.createElement('div');
    card.className = 'drone-card offline';
    card.id = 'dcard-' + id;
    card.onclick = () => openDroneControl(id);
    card.innerHTML = `
      <div class="drone-card-header">
        <span class="drone-id">${id}</span>
        <span class="drone-status-dot offline" id="ddot-${id}"></span>
      </div>
      <div class="drone-battery-row">
        <div class="drone-battery-track">
          <div class="drone-battery-fill" id="dbat-fill-${id}" style="width:0%;background:#d1d5db"></div>
        </div>
        <span class="drone-battery-pct" id="dbat-pct-${id}">--%</span>
      </div>
      <div class="drone-state-label">
        <span id="dalt-${id}">-- m</span>
        <span class="drone-state-badge" id="dstate-${id}">오프라인</span>
      </div>`;
    grid.appendChild(card);
    // select option
    const opt = document.createElement('option');
    opt.value = id; opt.textContent = id;
    sel.appendChild(opt);
  });
}
buildDroneGrid();

function updateDroneCard(id) {
  const d = droneData[id];
  const card = document.getElementById('dcard-' + id);
  const dot  = document.getElementById('ddot-' + id);
  const fill = document.getElementById('dbat-fill-' + id);
  const pct  = document.getElementById('dbat-pct-' + id);
  const alt  = document.getElementById('dalt-' + id);
  const st   = document.getElementById('dstate-' + id);
  if (!card) return;

  // connection
  card.classList.toggle('offline', !d.online);
  card.classList.toggle('selected', id === selectedDroneId);

  // dot
  const flying = d.mission_state === 'flying';
  const warn   = d.battery != null && d.battery < 20;
  dot.className = 'drone-status-dot ' + (!d.online ? 'offline' : flying ? 'flying' : warn ? 'warning' : 'online');

  // battery
  if (d.battery != null) {
    fill.style.width = d.battery + '%';
    fill.style.background = d.battery > 50 ? '#22c55e' : d.battery > 20 ? '#f59e0b' : '#ef4444';
    pct.textContent = d.battery + '%';
  }

  // altitude
  if (d.altitude != null) alt.textContent = d.altitude + ' m';

  // state badge
  const STATE_LABELS = {
    requested:'요청됨', preparing:'출발준비', flying:'비행중',
    arrived:'도착', delivered:'배송완료', failed:'실패', returning:'복귀중',
  };
  const stateKey = d.mission_state;
  st.textContent = !d.online ? '오프라인' : (STATE_LABELS[stateKey] ?? '대기중');
  st.className = 'drone-state-badge' +
    (!d.online ? '' : stateKey === 'flying' ? ' flying' : warn ? ' warning' :
     stateKey === 'delivered' ? ' done' : stateKey === 'failed' ? ' error' : '');
}

function updateSummary() {
  const vals = Object.values(droneData);
  const total   = vals.length;
  const online  = vals.filter(d => d.online).length;
  const flying  = vals.filter(d => d.mission_state === 'flying').length;
  const offline = vals.filter(d => !d.online).length;
  const warn    = vals.filter(d => d.online && d.battery != null && d.battery < 20).length;

  document.getElementById('sumTotal').textContent     = total;
  document.getElementById('sumOnlineTop').textContent = online;
  document.getElementById('sumFlyingTop').textContent = flying;
  document.getElementById('sumWarnTop').textContent   = warn;
  document.getElementById('sumOnline').textContent    = online;
  document.getElementById('sumFlying').textContent    = flying;
  document.getElementById('sumOffline').textContent   = offline;
  document.getElementById('navOnline').textContent    = online;

  const activeCount = flying;
  const badge = document.getElementById('navActive');
  badge.style.display = activeCount > 0 ? '' : 'none';
  badge.textContent = activeCount;
}

// ─── Open drone in control panel ──────────────────────────
function openDroneControl(id) {
  selectedDroneId = id;
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.querySelector('[data-page="control"]').classList.add('active');
  document.querySelectorAll('.page').forEach(pg => pg.classList.remove('active'));
  document.getElementById('page-control').classList.add('active');
  document.getElementById('topbarIcon').textContent  = PAGE_META.control.icon;
  document.getElementById('topbarTitle').textContent = PAGE_META.control.title;
  currentPage = 'control';
  document.getElementById('droneSelect').value = id;
  DRONE_IDS.forEach(did => document.getElementById('dcard-'+did)?.classList.toggle('selected', did === id));
  renderControlPanel();
  tryConnectVideo();
}

function selectDrone(id) {
  selectedDroneId = id;
  DRONE_IDS.forEach(did => document.getElementById('dcard-'+did)?.classList.toggle('selected', did === id));
  resetControlMission();
  renderControlPanel();
  tryConnectVideo();
}

// ─── Control panel rendering ───────────────────────────────
let ctrlMissionState = null;
let ctrlStTimestamps = {};
let ctrlFlightStart  = null;
let ctrlFlightTimer  = null;
let ctrlMaxAlt       = 0;

const STATES = [
  { id:'requested', label:'요청됨',   icon:'📋', desc:'배송 임무 요청 접수' },
  { id:'preparing', label:'출발준비', icon:'🔧', desc:'기체 점검 및 이륙 준비' },
  { id:'flying',    label:'비행중',   icon:'🚁', desc:'목적지 비행 중' },
  { id:'arrived',   label:'도착',     icon:'📍', desc:'목적지 도착' },
  { id:'delivered', label:'배송완료', icon:'✅', desc:'화물 투하 완료' },
  { id:'failed',    label:'배송실패', icon:'❌', desc:'배송 실패', isFail:true },
  { id:'returning', label:'복귀중',   icon:'↩️', desc:'기지 복귀 중' },
];

(function buildStateChain() {
  const chain = document.getElementById('stateChain');
  STATES.forEach(s => {
    const row = document.createElement('div');
    row.className = 'state-row';
    row.id = 'st-' + s.id;
    row.innerHTML = `
      <div class="state-connector"></div>
      <div class="state-node">${s.icon}</div>
      <div class="state-body">
        <div class="state-name">${s.label}</div>
        <div class="state-desc">${s.desc}</div>
        <div class="state-ts" id="st-ts-${s.id}"></div>
      </div>`;
    chain.appendChild(row);
  });
})();

function renderControlPanel() {
  const d = droneData[selectedDroneId];
  if (!d) return;
  applyControlStatus(d);
}

function applyControlStatus(d) {
  // battery
  if (d.battery != null) {
    document.getElementById('ctrlBattery').innerHTML = `${d.battery}<span class="ctrl-stat-unit">%</span>`;
    const fill = document.getElementById('ctrlBatteryFill');
    fill.style.width = d.battery + '%';
    fill.style.background = d.battery > 50 ? '#22c55e' : d.battery > 20 ? '#f59e0b' : '#ef4444';
    document.getElementById('ctrlBatteryEst').textContent = `약 ${Math.round(d.battery * 0.13)}분 추정`;
  }
  if (d.altitude != null) {
    document.getElementById('ctrlAltitude').innerHTML = `${d.altitude}<span class="ctrl-stat-unit">m</span>`;
    if (d.altitude > ctrlMaxAlt) {
      ctrlMaxAlt = d.altitude;
      document.getElementById('maxAlt').textContent = ctrlMaxAlt + ' m';
    }
  }
  if (d.speed    != null) document.getElementById('ctrlSpeed').textContent = `속도 ${d.speed} cm/s`;
  if (d.temp_low != null && d.temp_high != null) {
    document.getElementById('ctrlTemp').innerHTML = `${Math.round((d.temp_low+d.temp_high)/2)}<span class="ctrl-stat-unit">°C</span>`;
    document.getElementById('ctrlTempRange').textContent = `${d.temp_low} / ${d.temp_high}`;
  }
  if (d.yaw   != null) document.getElementById('yaw').textContent = d.yaw + '°';
  if (d.pitch != null && d.roll != null)
    document.getElementById('pitchRoll').textContent = `${d.pitch}° / ${d.roll}°`;
  if (d.lat != null && d.lon != null)
    document.getElementById('videoCoords').textContent = `LAT ${d.lat.toFixed(6)}  LON ${d.lon.toFixed(6)}`;
  if (d.mission_id)    document.getElementById('ctrlMissionId').textContent = 'ID: ' + d.mission_id;
  if (d.mission_state) setCtrlMissionState(d.mission_state);

  const isConn = d.online;
  document.getElementById('ctrlConnPill').textContent = isConn ? '● 연결됨' : '○ 미연결';
  document.getElementById('ctrlConnPill').style.color = isConn ? '#22c55e' : '#9ca3af';
  ['btnTakeoff','btnLand','btnReturn','btnHover','btnEmergency']
    .forEach(id => document.getElementById(id).disabled = !isConn);
}

function setCtrlMissionState(stateId) {
  if (ctrlMissionState === stateId) return;
  ctrlMissionState = stateId;
  const idx = STATES.findIndex(s => s.id === stateId);
  const now = new Date().toLocaleTimeString('ko-KR');
  STATES.forEach((s, i) => {
    const row = document.getElementById('st-' + s.id);
    const ts  = document.getElementById('st-ts-' + s.id);
    row.classList.remove('done','active','fail');
    if (s.isFail && stateId === 'failed') {
      row.classList.add('active');
      if (!ctrlStTimestamps[s.id]) { ctrlStTimestamps[s.id] = now; ts.textContent = now; }
    } else if (!s.isFail) {
      if (i < idx)       { row.classList.add('done');   if (!ctrlStTimestamps[s.id]) { ctrlStTimestamps[s.id]=now; ts.textContent=now; } }
      else if (i === idx){ row.classList.add('active'); if (!ctrlStTimestamps[s.id]) { ctrlStTimestamps[s.id]=now; ts.textContent=now; } }
    }
  });
  const label = STATES.find(s=>s.id===stateId)?.label ?? stateId;
  document.getElementById('ctrlMissionState').textContent = label;
  addLog(stateId==='failed'?'error':'ok', `[${selectedDroneId}] 임무 상태: ${label}`);
  if (stateId==='flying' && !ctrlFlightStart) {
    ctrlFlightStart = Date.now();
    ctrlFlightTimer = setInterval(() => {
      const s = Math.floor((Date.now()-ctrlFlightStart)/1000);
      document.getElementById('flightTime').textContent =
        String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0');
    }, 1000);
  }
  if ((stateId==='delivered'||stateId==='failed') && ctrlFlightTimer) {
    clearInterval(ctrlFlightTimer); ctrlFlightTimer=null;
  }
}

function resetControlMission() {
  ctrlMissionState = null; ctrlStTimestamps = {}; ctrlFlightStart = null; ctrlMaxAlt = 0;
  if (ctrlFlightTimer) { clearInterval(ctrlFlightTimer); ctrlFlightTimer = null; }
  STATES.forEach(s => {
    document.getElementById('st-'+s.id).classList.remove('done','active','fail');
    document.getElementById('st-ts-'+s.id).textContent = '';
  });
  document.getElementById('flightTime').textContent = '--:--';
  document.getElementById('maxAlt').textContent = '-- m';
  document.getElementById('ctrlMissionState').textContent = '대기중';
  document.getElementById('ctrlMissionId').textContent = 'ID: —';
}

// ─── Connection ────────────────────────────────────────────
let apiConnected = false;
function setApiConnected(val) {
  if (apiConnected === val) return;
  apiConnected = val;
  document.getElementById('sideConnDot').className = 'conn-dot' + (val?' on':'');
  document.getElementById('sideConnLabel').textContent = val ? 'API 서버 연결됨' : 'API 서버 미연결';
}

// ─── Video ─────────────────────────────────────────────────
function tryConnectVideo() {
  const img = document.getElementById('videoFeed');
  const ph  = document.getElementById('videoPlaceholder');
  const badge = document.getElementById('videoBadge');
  const src = CONFIG.API_BASE + CONFIG.STREAM_PATH + '?drone=' + selectedDroneId;
  img.onload  = () => { img.style.display='block'; ph.style.display='none'; badge.style.display='block'; };
  img.onerror = () => { img.style.display='none'; ph.style.display='flex'; badge.style.display='none'; };
  img.src = src + '&t=' + Date.now();
}

// ─── Logs ──────────────────────────────────────────────────
function addLog(level, msg) {
  [document.getElementById('logBody'), document.getElementById('fullLogBody')].forEach(body => {
    const now = new Date().toTimeString().slice(0,8);
    const div = document.createElement('div');
    div.className = 'log-entry';
    div.innerHTML = `<span class="log-time">${now}</span><span class="log-lv ${level}">${level.toUpperCase()}</span><span class="log-msg">${msg}</span>`;
    body.prepend(div);
    while (body.children.length > 300) body.lastChild.remove();
  });
}
function clearLogs() { document.getElementById('logBody').innerHTML = ''; addLog('info','로그 초기화됨'); }
function clearFullLogs() { document.getElementById('fullLogBody').innerHTML = ''; document.getElementById('logBody').innerHTML = ''; }

// ─── API polling ───────────────────────────────────────────
async function fetchAllStatus() {
  try {
    const res = await fetch(CONFIG.API_BASE + '/api/drones', { signal: AbortSignal.timeout(900) });
    if (!res.ok) throw new Error('HTTP '+res.status);
    const list = await res.json(); // [{id, ...}, ...]
    setApiConnected(true);
    list.forEach(d => {
      const id = d.id;
      if (!droneData[id]) return;
      Object.assign(droneData[id], {
        online: true,
        battery:      d.battery,
        altitude:     d.altitude,
        speed:        d.speed,
        temp_low:     d.temperature_low,
        temp_high:    d.temperature_high,
        yaw:          d.yaw,
        pitch:        d.pitch,
        roll:         d.roll,
        lat:          d.lat,
        lon:          d.lon,
        mission_id:   d.mission_id,
        mission_state:d.mission_state,
      });
      updateDroneCard(id);
    });
    // mark others offline
    const returnedIds = list.map(d => d.id);
    DRONE_IDS.forEach(id => {
      if (!returnedIds.includes(id)) {
        droneData[id].online = false;
        updateDroneCard(id);
      }
    });
    updateSummary();
    if (currentPage === 'control') applyControlStatus(droneData[selectedDroneId]);
  } catch {
    setApiConnected(false);
    DRONE_IDS.forEach(id => { droneData[id].online = false; updateDroneCard(id); });
    updateSummary();
  }
}

async function fetchLogs() {
  try {
    const res = await fetch(`${CONFIG.API_BASE}/api/logs?limit=20`, { signal: AbortSignal.timeout(900) });
    if (!res.ok) return;
    const logs = await res.json();
    logs.forEach(l => addLog(l.level||'info', l.msg));
  } catch { /* silent */ }
}

async function fetchMissions() {
  try {
    const res = await fetch(CONFIG.API_BASE + '/api/missions', { signal: AbortSignal.timeout(900) });
    if (!res.ok) return;
    const missions = await res.json();
    renderMissionTable(missions);
  } catch { /* silent */ }
}

function renderMissionTable(missions) {
  const tbody = document.getElementById('missionTableBody');
  if (!missions || missions.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:30px">임무 데이터 없음</td></tr>';
    return;
  }
  const STATE_BADGE = {
    requested:'gray', preparing:'yellow', flying:'blue',
    arrived:'blue', delivered:'green', failed:'red', returning:'yellow',
  };
  const STATE_KO = {
    requested:'요청됨', preparing:'출발준비', flying:'비행중',
    arrived:'도착', delivered:'배송완료', failed:'실패', returning:'복귀중',
  };
  tbody.innerHTML = missions.map(m => `
    <tr>
      <td>${m.id ?? '—'}</td>
      <td>${m.drone_id ?? '—'}</td>
      <td>${m.origin ?? '—'}</td>
      <td>${m.destination ?? '—'}</td>
      <td><span class="badge ${STATE_BADGE[m.state]??'gray'}">${STATE_KO[m.state]??m.state??'—'}</span></td>
      <td>${m.started_at ?? '—'}</td>
    </tr>`).join('');
}

// ─── Drone commands ────────────────────────────────────────
async function droneCmd(cmd) {
  addLog('info', `[${selectedDroneId}] 명령: ${cmd}`);
  try {
    const res = await fetch(`${CONFIG.API_BASE}/api/command/${cmd}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ drone_id: selectedDroneId }),
      signal: AbortSignal.timeout(3000),
    });
    const d = await res.json();
    addLog(res.ok?'ok':'error', `[${selectedDroneId}][${cmd}] ${d.message??(res.ok?'성공':'실패')}`);
  } catch(e) {
    addLog('error', `[${selectedDroneId}][${cmd}] 실패: ${e.message}`);
  }
}

// ─── Settings ─────────────────────────────────────────────
function applySettings() {
  CONFIG.API_BASE    = document.getElementById('settingApiBase').value.trim();
  CONFIG.STREAM_PATH = document.getElementById('settingStreamPath').value.trim();
  CONFIG.POLL_MS     = parseInt(document.getElementById('settingPollMs').value) || 1000;
  document.getElementById('apiEndpoint').textContent = CONFIG.API_BASE;
  addLog('ok', '설정 저장됨');
}

// ─── Keyboard shortcuts ────────────────────────────────────
document.addEventListener('keydown', e => {
  if (!droneData[selectedDroneId]?.online) return;
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
  switch(e.key) {
    case 'T': case 't': droneCmd('takeoff'); break;
    case 'L': case 'l': droneCmd('land');    break;
    case 'R': case 'r': droneCmd('return');  break;
    case 'H': case 'h': droneCmd('hover');   break;
    case 'E': case 'e': if (e.shiftKey) droneCmd('emergency'); break;
  }
});

// ─── Init ──────────────────────────────────────────────────
DRONE_IDS.forEach(id => updateDroneCard(id));
updateSummary();
addLog('info', '관제 시스템 초기화 완료');
addLog('info', `API: ${CONFIG.API_BASE}  |  기체 ${CONFIG.DRONE_COUNT}대 등록`);

fetchAllStatus();
setInterval(fetchAllStatus, CONFIG.POLL_MS);
setTimeout(() => { fetchLogs(); fetchMissions(); setInterval(fetchLogs, 5000); setInterval(fetchMissions, 10000); }, 2000);
tryConnectVideo();
setInterval(tryConnectVideo, 10000);
</script>
</body>
</html>
