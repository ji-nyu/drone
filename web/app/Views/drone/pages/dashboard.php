<!-- Dashboard page -->
<div class="summary-row">
  <div class="summary-card">
    <div class="summary-card-label">전체 기체</div>
    <div class="summary-card-value" id="dSumTotal">0</div>
    <div class="summary-card-sub">등록된 드론</div>
  </div>
  <div class="summary-card">
    <div class="summary-card-label">비행 중</div>
    <div class="summary-card-value blue" id="dSumFlying">0</div>
    <div class="summary-card-sub">현재 임무 수행</div>
  </div>
  <div class="summary-card">
    <div class="summary-card-label">온라인</div>
    <div class="summary-card-value green" id="dSumOnline">0</div>
    <div class="summary-card-sub">연결 정상</div>
  </div>
  <div class="summary-card">
    <div class="summary-card-label">경고</div>
    <div class="summary-card-value red" id="dSumWarn">0</div>
    <div class="summary-card-sub">배터리 20% 이하</div>
  </div>
</div>

<div class="section-title">기체 현황</div>
<div class="drone-grid" id="droneGrid"></div>

<div class="section-title" style="margin-top:30px;">해양쓰레기 탐지 지도</div>
<div id="trashMap" style="width:100%; height:420px; border-radius:12px; background:#eee;"></div>

<div class="section-title" style="margin-top:30px;">해양쓰레기 탐지 현황</div>

<div class="summary-row">
  <div class="summary-card">
    <div class="summary-card-label">총 탐지 수</div>
    <div class="summary-card-value" id="trashTotal">0</div>
    <div class="summary-card-sub">CSV 누적 기준</div>
  </div>

  <div class="summary-card">
    <div class="summary-card-label">최근 탐지</div>
    <div class="summary-card-value" id="trashRecent">-</div>
    <div class="summary-card-sub">class</div>
  </div>

  <div class="summary-card">
    <div class="summary-card-label">위험 구역</div>
    <div class="summary-card-value red" id="trashTopZone">-</div>
    <div class="summary-card-sub">탐지 최다 zone</div>
  </div>
</div>

<div style="margin-top:16px; background:white; border-radius:12px; padding:16px;">
  <div style="font-weight:700; margin-bottom:10px;">최근 탐지 로그</div>
  <table style="width:100%; border-collapse:collapse; font-size:13px;">
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

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>window.PAGE = 'dashboard';</script>
