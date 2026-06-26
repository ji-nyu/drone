<!-- Control page -->
<div class="ctrl-left">
  <div class="ctrl-topbar">
    <select class="drone-select" id="droneSelect" onchange="changeDrone(this.value)"></select>
    <button class="btn primary" id="btnConnect"    onclick="connectDrone()"    style="font-size:11px;padding:4px 10px">● 연결</button>
    <button class="btn"         id="btnDisconnect" onclick="disconnectDrone()" style="font-size:11px;padding:4px 10px;display:none">○ 연결 해제</button>
    <span id="ctrlConn" style="font-size:11px;color:#9ca3af">미연결</span>
  </div>

  <div class="ctrl-stat-bar">
    <div class="ctrl-stat">
      <div class="ctrl-stat-label">배터리</div>
      <div class="ctrl-stat-value" id="ctrlBattery">--<span class="ctrl-stat-unit">%</span></div>
      <div class="ctrl-bat-bar"><div class="ctrl-bat-fill" id="ctrlBatFill" style="width:0%"></div></div>
      <div class="ctrl-stat-sub" id="ctrlBatEst">— 분 추정</div>
      <div class="ctrl-stat-sub" id="ctrlBatRange" style="color:#1d4ed8">편도 -- m</div>
    </div>
    <div class="ctrl-stat">
      <div class="ctrl-stat-label">고도</div>
      <div class="ctrl-stat-value" id="ctrlAlt">--<span class="ctrl-stat-unit">m</span></div>
      <div class="ctrl-stat-sub" id="ctrlSpeed">속도 —</div>
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
    <img class="video-feed" id="videoFeed" alt="" style="display:none;width:100%;height:100%;object-fit:contain;background:#000">
    <div class="video-ph" id="videoPh">
      <div class="video-ph-icon">📡</div>
      <p>영상 스트림 대기 중</p>
      <small>Python API 연결 후 자동으로 표시됩니다</small>
    </div>
    <div class="live-badge" id="liveBadge">● LIVE</div>
    <div class="video-coords" id="videoCoords"></div>
  </div>

  <div class="log-area">
    <div class="area-header">
      <span class="area-title">비행 / VLM 로그</span>
      <button class="btn-xs" onclick="clearLog()">지우기</button>
    </div>
    <div class="log-body" id="logBody"></div>
  </div>
</div>

<div class="ctrl-right">
  <!-- 상태 머신 -->
  <!--
  <div class="rpanel-section">
    <div class="rpanel-title">임무 진행 상태</div>
    <div class="state-list" id="stateChain"></div>
  </div>
  -->

  <!-- 제어 버튼 -->
  <div class="rpanel-section">
    <div class="rpanel-title">드론 제어</div>
    <div class="btn-grid" style="margin-top:0">
      <button class="btn primary" id="btnTakeoff"   onclick="droneCmd('takeoff')"  >↑ 이륙</button>
      <button class="btn"         id="btnLand"      onclick="droneCmd('land')"     >↓ 착륙</button>
      <button class="btn"         id="btnReturn"    onclick="droneCmd('return')"   >⟳ 귀환</button>
      <button class="btn"         id="btnHover"     onclick="droneCmd('hover')"    >◎ 정지비행</button>
      <button class="btn danger full" id="btnEmergency" onclick="droneCmd('emergency')">⚠ 긴급정지</button>
      <button class="btn full" id="btnRetry" onclick="droneCmd('retry')" style="display:none;border-color:#f59e0b;color:#b45309">↺ 실패 재시도</button>
    </div>
  </div>

  <!-- 키보드 조작 -->
  <div class="rpanel-section">
    <div class="rpanel-title" style="display:flex;justify-content:space-between;align-items:center">
      키보드 조작
      <label style="font-size:11px;font-weight:400;display:flex;align-items:center;gap:4px;cursor:pointer">
        <input type="checkbox" id="kbdEnable"> 활성화
      </label>
    </div>
    <div style="font-size:11px;color:#9ca3af;margin-bottom:8px">W/S: 상승·하강 &nbsp;|&nbsp; A/D: 좌우회전 &nbsp;|&nbsp; 방향키: 전후좌우</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:3px;margin-bottom:6px">
      <div class="kbd-key empty"></div>
      <div class="kbd-key" id="k-keyw">W<br><span style="font-size:9px;font-weight:400">상승</span></div>
      <div class="kbd-key empty"></div>
      <div class="kbd-key" id="k-keya">A<br><span style="font-size:9px;font-weight:400">좌회전</span></div>
      <div class="kbd-key empty" style="font-size:14px">●</div>
      <div class="kbd-key" id="k-keyd">D<br><span style="font-size:9px;font-weight:400">우회전</span></div>
      <div class="kbd-key empty"></div>
      <div class="kbd-key" id="k-keys">S<br><span style="font-size:9px;font-weight:400">하강</span></div>
      <div class="kbd-key empty"></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:3px;margin-bottom:8px">
      <div class="kbd-key empty"></div>
      <div class="kbd-key" id="k-arrowup">↑<br><span style="font-size:9px;font-weight:400">전진</span></div>
      <div class="kbd-key empty"></div>
      <div class="kbd-key" id="k-arrowleft">←<br><span style="font-size:9px;font-weight:400">좌</span></div>
      <div class="kbd-key empty" style="font-size:14px">●</div>
      <div class="kbd-key" id="k-arrowright">→<br><span style="font-size:9px;font-weight:400">우</span></div>
      <div class="kbd-key empty"></div>
      <div class="kbd-key" id="k-arrowdown">↓<br><span style="font-size:9px;font-weight:400">후진</span></div>
      <div class="kbd-key empty"></div>
    </div>
    <div style="font-size:11px;color:#6b7280">
      RC: <span id="kbdValues" style="font-family:monospace;color:#374151">lr=0 fb=0 ud=0 yaw=0</span>
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
      <div class="info-cell"><div class="info-cell-label">Pitch / Roll</div><div class="info-cell-value" id="pitchRoll">-- / --</div></div>
      <div class="info-cell full"><div class="info-cell-label">API 서버</div><div class="info-cell-value muted" id="apiEndpoint"></div></div>
    </div>
  </div>
</div>

<script>
window.PAGE = 'control';
window.PAGE_DATA = { selectedDrone: '<?= esc($selectedDrone ?? 'TT-01', 'js') ?>' };
</script>
