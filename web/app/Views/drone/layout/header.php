<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? '관제' ?> — Waveon</title>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
<script>
const CONFIG = {
  API_BASE:     '<?= esc($cfgApiBase,     'js') ?>',
  API_TOKEN:    '<?= esc($cfgApiToken ?? '', 'js') ?>',
  STREAM_PATH:  '<?= esc($cfgStreamPath,  'js') ?>',
  POLL_MS:      <?= (int) $cfgPollMs ?>,
  DRONE_COUNT:  <?= (int) $cfgDroneCount ?>,
  DRONE_PREFIX: '<?= esc($cfgDronePrefix, 'js') ?>',
};
(function() {
  const saved = JSON.parse(localStorage.getItem('droneControlConfig') || '{}');
  if (Object.keys(saved).length > 0) Object.assign(CONFIG, saved);
})();

const DRONE_IDS = <?= json_encode(array_column($droneList ?? [], 'drone_id')) ?>;
const DRONE_MAP = <?= json_encode(array_column($droneList ?? [], 'ip', 'drone_id')) ?>;
</script>
<link rel="stylesheet" href="/css/main.css">
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon" aria-hidden="true">
      <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="16" cy="16" r="15" fill="#3B82F6"/>
        <path d="M7 18c3.5-4 7-6 9-6s5.5 2 9 6" stroke="#fff" stroke-width="2.2" stroke-linecap="round" fill="none"/>
        <path d="M9 22c2.8-3.2 5.5-4.8 7-4.8s4.2 1.6 7 4.8" stroke="#fff" stroke-width="1.8" stroke-linecap="round" fill="none" opacity=".7"/>
      </svg>
    </div>
    <div>
      <div class="sidebar-logo-title">Waveon</div>
      <div class="sidebar-logo-sub">드론 관제 시스템</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">메인</div>
    <a href="/drone" class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="2.5" width="6.5" height="6.5" rx="1.5"/><rect x="11" y="2.5" width="6.5" height="6.5" rx="1.5"/><rect x="2.5" y="11" width="6.5" height="6.5" rx="1.5"/><rect x="11" y="11" width="6.5" height="6.5" rx="1.5"/></svg>
      </span>
      대시보드
      <span class="nav-badge blue" id="navOnline" style="display:none">0</span>
    </a>
    <a href="/drone/control" class="nav-item <?= ($activePage ?? '') === 'control' ? 'active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 3.5l1.2 3.2h3.4l-2.7 2 1 3.3L10 10.5 7.1 12l1-3.3-2.7-2h3.4L10 3.5z"/><circle cx="10" cy="15.5" r="1.2" fill="currentColor" stroke="none"/></svg>
      </span>
      드론 관제
    </a>

    <div class="nav-section-label">운영</div>
    <a href="/drone/marine-trash" class="nav-item <?= ($activePage ?? '') === 'marine_trash' ? 'active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 7.5h10v8.5a1.5 1.5 0 01-1.5 1.5h-7A1.5 1.5 0 015 16V7.5z"/><path d="M3.5 7.5h13M8 7.5V5.5a2 2 0 014 0v2"/></svg>
      </span>
      해양쓰레기
    </a>
    <a href="/drone/missions" class="nav-item <?= ($activePage ?? '') === 'missions' ? 'active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="2.5" width="12" height="15" rx="1.5"/><path d="M7 7h6M7 10.5h6M7 14h3.5"/></svg>
      </span>
      임무 현황
      <span class="nav-badge red" id="navFlying" style="display:none">0</span>
    </a>
    <a href="/drone/logs" class="nav-item <?= ($activePage ?? '') === 'logs' ? 'active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="7"/><circle cx="10" cy="10" r="3.5"/><circle cx="10" cy="10" r="1" fill="currentColor" stroke="none"/></svg>
      </span>
      비행 로그
    </a>

    <div class="nav-section-label">시스템</div>
    <a href="/drone/settings" class="nav-item <?= ($activePage ?? '') === 'settings' ? 'active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="2.5"/><path d="M10 2.5v2M10 15.5v2M2.5 10h2M15.5 10h2M4.7 4.7l1.4 1.4M13.9 13.9l1.4 1.4M4.7 15.3l1.4-1.4M13.9 6.1l1.4-1.4"/></svg>
      </span>
      설정
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
      <div class="clock-pill">
        <svg class="clock-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6"/><path d="M8 4.5V8l2.5 1.5"/></svg>
        <span class="clock" id="clock">--:--:--</span>
      </div>
      <button type="button" class="topbar-icon-btn" aria-label="알림">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 2.5a4.5 4.5 0 014.5 4.5v2.2c0 .7.3 1.4.7 1.9L16.5 13H3.5l1.3-1.9c.4-.5.7-1.2.7-1.9V7A4.5 4.5 0 0110 2.5z"/><path d="M8 15.5a2 2 0 004 0"/></svg>
        <span class="notif-dot"></span>
      </button>
      <div class="topbar-user">
        <div class="user-avatar" aria-hidden="true">
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="7" r="3"/><path d="M4 16.5c1.5-2.5 3.5-3.5 6-3.5s4.5 1 6 3.5"/></svg>
        </div>
        <span class="user-name">관리자</span>
        <svg class="user-chevron" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4.5l3 3 3-3"/></svg>
      </div>
    </div>
  </div>
  <div class="page-content <?= $contentClass ?? '' ?>">
