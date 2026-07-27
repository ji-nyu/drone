<!-- Dashboard — Waveon -->
<div class="dash-hero-row">
  <div class="dash-summary-panel">
    <div class="dash-stat">
      <div class="dash-stat-icon blue">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 4l1.5 4H18l-3.5 2.5L16 15l-4-2.5L8 15l1.5-4.5L6 8h4.5L12 4z"/><circle cx="12" cy="18.5" r="1.5"/></svg>
      </div>
      <div>
        <div class="dash-stat-label">전체 기체</div>
        <div class="dash-stat-value" id="dSumTotal">0</div>
        <div class="dash-stat-sub">등록된 드론</div>
      </div>
    </div>
    <div class="dash-stat">
      <div class="dash-stat-icon blue">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 12l18-8-4 16-6-5-8-3z"/></svg>
      </div>
      <div>
        <div class="dash-stat-label">비행 중</div>
        <div class="dash-stat-value" id="dSumFlying">0</div>
        <div class="dash-stat-sub">현재 임무 수행</div>
      </div>
    </div>
    <div class="dash-stat">
      <div class="dash-stat-icon green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg>
      </div>
      <div>
        <div class="dash-stat-label">온라인</div>
        <div class="dash-stat-value green" id="dSumOnline">0</div>
        <div class="dash-stat-sub">연결 정상</div>
      </div>
    </div>
    <div class="dash-stat">
      <div class="dash-stat-icon red">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3L21 19H3L12 3z"/><path d="M12 10v4M12 16.5v.5"/></svg>
      </div>
      <div>
        <div class="dash-stat-label">경고</div>
        <div class="dash-stat-value red" id="dSumWarn">0</div>
        <div class="dash-stat-sub">배터리 20% 이하</div>
      </div>
    </div>
  </div>

  <div class="dash-calendar card" id="dashCalendar">
    <div class="cal-header">
      <button type="button" class="cal-nav" id="calPrev" aria-label="이전 달">‹</button>
      <div class="cal-title" id="calTitle">—</div>
      <button type="button" class="cal-nav" id="calNext" aria-label="다음 달">›</button>
    </div>
    <div class="cal-weekdays">
      <span>일</span><span>월</span><span>화</span><span>수</span><span>목</span><span>금</span><span>토</span>
    </div>
    <div class="cal-days" id="calDays"></div>
  </div>
</div>

<div class="section-title">기체 현황</div>
<div class="drone-grid" id="droneGrid"></div>

<div class="dash-kpi-row">
  <div class="kpi-card kpi-primary">
    <div class="kpi-top">
      <span class="kpi-label">총 탐지수</span>
      <span class="kpi-icon">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="9" r="5.5"/><path d="M13.5 13.5L17 17"/></svg>
      </span>
    </div>
    <div class="kpi-value" id="trashTotal">0</div>
    <div class="kpi-sub">CSV 누적 기준</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-top">
      <span class="kpi-label">최근 탐지</span>
      <span class="kpi-icon muted">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 3.5h4l1 3.5H7L8 3.5z"/><path d="M6.5 7h7v8.5a1.5 1.5 0 01-1.5 1.5h-4A1.5 1.5 0 016.5 15.5V7z"/><path d="M9 11.5h2"/></svg>
      </span>
    </div>
    <div class="kpi-value accent" id="trashRecent">—</div>
    <div class="kpi-sub">class</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-top">
      <span class="kpi-label">위험구역</span>
      <span class="kpi-icon muted">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 3L17.5 16H2.5L10 3z"/><path d="M10 8.5v4M10 14.5v.5"/></svg>
      </span>
    </div>
    <div class="kpi-value danger" id="trashTopZone">—</div>
    <div class="kpi-sub">탐지 최다 Zone</div>
  </div>

  <div class="kpi-card kpi-chart">
    <div class="kpi-top">
      <span class="kpi-label">신뢰도</span>
    </div>
    <div class="kpi-chart-wrap">
      <canvas id="confChart" height="70"></canvas>
    </div>
  </div>
</div>

<div class="card dash-log-card">
  <div class="card-header">
    <div class="card-title dash-log-title">
      <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2.5" y="1.5" width="11" height="13" rx="1.2"/><path d="M5 5h6M5 8h6M5 11h3.5"/></svg>
      최근 탐지 로그
    </div>
  </div>
  <div class="dash-log-table-wrap">
    <table class="dash-log-table">
      <thead>
        <tr>
          <th>시간</th>
          <th>구역</th>
          <th>종류</th>
          <th>신뢰도</th>
        </tr>
      </thead>
      <tbody id="trashLogTable"></tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>window.PAGE = 'dashboard';</script>
