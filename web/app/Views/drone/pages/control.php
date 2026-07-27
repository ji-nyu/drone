<!-- Control — Waveon -->
<div class="ctrl-left">
  <div class="ctrl-topbar">
    <select class="drone-select" id="droneSelect" onchange="changeDrone(this.value)"></select>
    <div class="conn-toggle">
      <button type="button" class="conn-pill primary" id="btnConnect" onclick="connectDrone()">연결</button>
      <button type="button" class="conn-pill" id="btnDisconnect" onclick="disconnectDrone()" style="display:none">연결 해제</button>
    </div>
    <span class="conn-status" id="ctrlConn">미연결</span>
  </div>

  <div class="ctrl-stat-bar">
    <div class="ctrl-stat">
      <div class="ctrl-stat-label">배터리</div>
      <div class="ctrl-stat-value" id="ctrlBattery">--<span class="ctrl-stat-unit">%</span></div>
      <div class="ctrl-bat-bar"><div class="ctrl-bat-fill" id="ctrlBatFill" style="width:0%"></div></div>
      <div class="ctrl-stat-sub" id="ctrlBatEst">— 분 추정</div>
      <div class="ctrl-stat-sub accent" id="ctrlBatRange">편도 -- m</div>
    </div>
    <div class="ctrl-stat">
      <div class="ctrl-stat-label">고도 / 속도</div>
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
      <div class="ctrl-stat-value mission" id="ctrlMissionState">대기중</div>
      <div class="ctrl-stat-sub" id="ctrlMissionId">ID: —</div>
    </div>
  </div>

  <div class="video-area">
    <img class="video-feed" id="videoFeed" alt="" style="display:none;width:100%;height:100%;object-fit:contain;background:#000">
    <div class="video-ph" id="videoPh">
      <div class="video-ph-icon" aria-hidden="true">
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
          <rect x="6" y="12" width="28" height="22" rx="3"/>
          <path d="M34 20l8-5v18l-8-5V20z"/>
          <circle cx="20" cy="23" r="4"/>
        </svg>
      </div>
      <p>영상 스트림 대기 중</p>
      <small>Python API 연결 후 자동으로 표시됩니다</small>
    </div>
    <div class="live-badge" id="liveBadge">● LIVE</div>
    <div class="video-coords" id="videoCoords"></div>
  </div>

  <div class="log-area">
    <div class="area-header">
      <span class="area-title">비행 로그</span>
      <button class="btn-xs" onclick="clearLog()">지우기</button>
    </div>
    <div class="log-body" id="logBody"></div>
  </div>
</div>

<div class="ctrl-right">
  <div class="rpanel-section">
    <div class="rpanel-title">드론 제어</div>
    <div class="btn-grid">
      <button class="btn primary" id="btnTakeoff" onclick="droneCmd('takeoff')">↑ 이륙</button>
      <button class="btn" id="btnLand" onclick="droneCmd('land')">↓ 착륙</button>
      <button class="btn" id="btnReturn" onclick="droneCmd('return')">⟳ 귀환</button>
      <button class="btn" id="btnHover" onclick="droneCmd('hover')">◎ 정지비행</button>
      <button class="btn danger full" id="btnEmergency" onclick="droneCmd('emergency')">⚠ 긴급정지</button>
      <button class="btn full btn-retry" id="btnRetry" onclick="droneCmd('retry')" style="display:none">↺ 실패 재시도</button>
    </div>
  </div>

  <div class="rpanel-section">
    <div class="rpanel-title kbd-title">
      <span>키보드 조작</span>
      <label class="kbd-enable">
        <input type="checkbox" id="kbdEnable"> 활성화
      </label>
    </div>
    <div class="kbd-hint">W/S 상승·하강 · A/D 좌우회전 · 방향키 전후좌우</div>
    <div class="kbd-grid">
      <div class="kbd-key empty"></div>
      <div class="kbd-key" id="k-keyw">W<span>상승</span></div>
      <div class="kbd-key empty"></div>
      <div class="kbd-key" id="k-keya">A<span>좌회전</span></div>
      <div class="kbd-key empty center-dot">●</div>
      <div class="kbd-key" id="k-keyd">D<span>우회전</span></div>
      <div class="kbd-key empty"></div>
      <div class="kbd-key" id="k-keys">S<span>하강</span></div>
      <div class="kbd-key empty"></div>
    </div>
    <div class="kbd-grid">
      <div class="kbd-key empty"></div>
      <div class="kbd-key" id="k-arrowup">↑<span>전진</span></div>
      <div class="kbd-key empty"></div>
      <div class="kbd-key" id="k-arrowleft">←<span>좌</span></div>
      <div class="kbd-key empty center-dot">●</div>
      <div class="kbd-key" id="k-arrowright">→<span>우</span></div>
      <div class="kbd-key empty"></div>
      <div class="kbd-key" id="k-arrowdown">↓<span>후진</span></div>
      <div class="kbd-key empty"></div>
    </div>
    <div class="kbd-rc">
      RC: <span id="kbdValues">lr=0 fb=0 ud=0 yaw=0</span>
    </div>
  </div>

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
