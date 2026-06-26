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

<script>window.PAGE = 'dashboard';</script>
