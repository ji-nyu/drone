<!-- Logs page -->
<div style="display:flex;align-items:center;justify-content:space-between">
  <div class="section-title" style="margin:0">비행 로그</div>
  <div id="logFilterBar" style="display:flex;gap:8px;align-items:center">
    <select id="logDroneFilter" style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:4px 8px;font-size:12px;color:#374151;outline:none" onchange="applyFilter()">
      <option value="">전체 드론</option>
    </select>
    <select id="logLevelFilter" style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:4px 8px;font-size:12px;color:#374151;outline:none" onchange="applyFilter()">
      <option value="">전체 레벨</option>
      <option value="info">INFO</option>
      <option value="ok">OK</option>
      <option value="warn">WARN</option>
      <option value="error">ERROR</option>
    </select>
    <button class="btn-xs" onclick="clearFullLogs()">전체 지우기</button>
  </div>
  <div id="apiFilterBar" style="display:none;gap:8px;align-items:center">
    <button class="btn-xs" onclick="clearApiEvents()">이벤트 지우기</button>
  </div>
</div>

<div class="tab-bar">
  <button class="tab-btn active" id="tab-flight" onclick="switchTab('flight')">비행 로그</button>
  <button class="tab-btn"        id="tab-api"    onclick="switchTab('api')">API 이벤트</button>
</div>

<!-- 비행 로그 패널 -->
<div id="panelFlight">
  <div class="table-wrap" style="overflow:hidden;margin-top:12px">
    <div id="fullLogBody" class="log-body" style="height:calc(100vh - 218px)"></div>
  </div>
</div>

<!-- API 이벤트 패널 -->
<div id="panelApi" style="display:none">
  <div class="table-wrap" style="overflow:hidden;margin-top:12px">
    <div id="apiEventBody" style="height:calc(100vh - 218px);overflow-y:auto;scrollbar-width:thin;scrollbar-color:#e5e7eb transparent"></div>
  </div>
</div>

<script>window.PAGE = 'logs';</script>
