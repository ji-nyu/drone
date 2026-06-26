<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? '관제' ?> — DroneControl</title>
<script>
const CONFIG = {
  API_BASE:     '<?= esc($cfgApiBase,     'js') ?>',
  API_TOKEN:    '<?= esc($cfgApiToken ?? '', 'js') ?>',
  STREAM_PATH:  '<?= esc($cfgStreamPath,  'js') ?>',
  POLL_MS:      <?= (int) $cfgPollMs ?>,
  DRONE_COUNT:  <?= (int) $cfgDroneCount ?>,
  DRONE_PREFIX: '<?= esc($cfgDronePrefix, 'js') ?>',
};
// Apply saved config immediately (settings page writes to 'droneControlConfig')
(function() {
  const saved = JSON.parse(localStorage.getItem('droneControlConfig') || '{}');
  if (Object.keys(saved).length > 0) Object.assign(CONFIG, saved);
})();

// Build DRONE_IDS / DRONE_MAP from server-side DB (App\Models\DroneModel)
const DRONE_IDS = <?= json_encode(array_column($droneList ?? [], 'drone_id')) ?>;
// IP lookup: DRONE_MAP['TT-01'] === '192.168.1.10'
const DRONE_MAP = <?= json_encode(array_column($droneList ?? [], 'ip', 'drone_id')) ?>;
</script>
<link rel="stylesheet" href="/css/main.css">
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon">🚁</div>
    <div>
      <div class="sidebar-logo-title">DroneControl</div>
      <div class="sidebar-logo-sub">드론택배</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">메인</div>
    <a href="/drone" class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
      <span class="nav-icon">▦</span> 대시보드
      <span class="nav-badge blue" id="navOnline" style="display:none">0</span>
    </a>
    <a href="/drone/control" class="nav-item <?= ($activePage ?? '') === 'control' ? 'active' : '' ?>">
      <span class="nav-icon">✦</span> 드론 관제
    </a>

    <div class="nav-section-label">운영</div>
    <a href="/drone/missions" class="nav-item <?= ($activePage ?? '') === 'missions' ? 'active' : '' ?>">
      <span class="nav-icon">≡</span> 임무 현황
      <span class="nav-badge red" id="navFlying" style="display:none">0</span>
    </a>
    <a href="/drone/logs" class="nav-item <?= ($activePage ?? '') === 'logs' ? 'active' : '' ?>">
      <span class="nav-icon">◈</span> 비행 로그
    </a>

    <div class="nav-section-label">시스템</div>
    <a href="/drone/settings" class="nav-item <?= ($activePage ?? '') === 'settings' ? 'active' : '' ?>">
      <span class="nav-icon">⊙</span> 설정
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-conn">
      <div class="conn-dot" id="sideConnDot"></div>
      <span id="sideConnLabel">API 서버 미연결</span>
    </div>
    <div class="drone-summary">
      <span><span class="dot-sm" style="background:#22c55e"></span><span id="sumOnline">0</span> 온라인</span>
      <span><span class="dot-sm" style="background:#3b82f6"></span><span id="sumFlying">0</span> 비행중</span>
      <span><span class="dot-sm" style="background:#d1d5db"></span><span id="sumOffline">0</span> 오프</span>
    </div>
  </div>
</aside>

<div class="main-wrap">
  <div class="topbar">
    <div class="topbar-left">
      <span class="page-icon"><?= $pageIcon ?? '' ?></span>
      <span><?= $pageTitle ?? '' ?></span>
    </div>
    <div class="topbar-right">
      <span class="clock" id="clock">--:--:--</span>
    </div>
  </div>
  <div class="page-content <?= $contentClass ?? '' ?>">
