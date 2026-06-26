<!-- Settings page -->
<div class="section-title">시스템 설정</div>

<div class="settings-card">
  <div class="settings-card-header">API 서버 연결</div>
  <div class="settings-row">
    <div>
      <div class="settings-label">Python API 서버 주소</div>
      <div class="settings-desc">tello.api_url (.env)</div>
    </div>
    <input class="settings-input" id="sTelloUrl" placeholder="http://127.0.0.1:8000">
  </div>
  <div class="settings-row">
    <div>
      <div class="settings-label">Authorization 토큰</div>
      <div class="settings-desc">tello.api_token (.env)</div>
    </div>
    <input class="settings-input" id="sTelloToken" placeholder="tello-api-secret-change-me">
  </div>
  <div class="settings-row">
    <div>
      <div class="settings-label">API 타임아웃</div>
      <div class="settings-desc">tello.api_timeout (.env) — 초 단위</div>
    </div>
    <input class="settings-input" id="sTelloTimeout" type="number" min="1" max="60" placeholder="10">
  </div>
  <div class="settings-row">
    <div>
      <div class="settings-label">영상 스트림 경로</div>
      <div class="settings-desc">ffmpeg MJPEG 스트림 경로</div>
    </div>
    <input class="settings-input" id="sStreamPath" value="/video/stream.mjpeg">
  </div>
  <div class="settings-row">
    <div>
      <div class="settings-label">상태 폴링 주기</div>
      <div class="settings-desc">드론 상태 조회 간격 (ms, 최소 500)</div>
    </div>
    <input class="settings-input" id="sPollMs" type="number" min="500" max="10000" value="1000">
  </div>
</div>

<div class="settings-card">
  <div class="settings-card-header">
    드론 등록 목록
    <span style="font-size:11px;font-weight:400;color:#9ca3af;margin-left:8px">IP 주소로 각 기체를 등록합니다</span>
  </div>
  <div id="droneListWrap" style="padding:0 18px 4px"></div>
  <div class="settings-row" style="gap:8px;flex-wrap:wrap;align-items:flex-end">
    <div style="display:flex;gap:6px;flex:1;min-width:0">
      <div style="flex:1;min-width:80px">
        <div class="settings-desc" style="margin-bottom:4px">이름 / ID</div>
        <input class="settings-input" id="newDroneName" placeholder="예: TT-01" style="width:100%">
      </div>
      <div style="flex:1.5;min-width:120px">
        <div class="settings-desc" style="margin-bottom:4px">IP 주소</div>
        <input class="settings-input" id="newDroneIp" placeholder="예: 192.168.1.10" style="width:100%">
      </div>
    </div>
    <button class="btn primary" style="width:auto;padding:8px 16px;white-space:nowrap;flex-shrink:0" onclick="addDrone()">+ 드론 추가</button>
  </div>
</div>

<div class="settings-card">
  <div class="settings-card-header">
    기본값 설정
    <span style="font-size:11px;font-weight:400;color:#9ca3af;margin-left:8px">드론 목록 미등록 시 자동 생성</span>
  </div>
  <div class="settings-row">
    <div>
      <div class="settings-label">기체 수</div>
      <div class="settings-desc">1~20대</div>
    </div>
    <input class="settings-input" id="sDroneCount" type="number" min="1" max="20" value="10">
  </div>
  <div class="settings-row">
    <div>
      <div class="settings-label">기체 ID 접두사</div>
      <div class="settings-desc">예: TT → TT-01, TT-02 ...</div>
    </div>
    <input class="settings-input" id="sDronePrefix" value="TT">
  </div>
</div>

<div class="settings-card">
  <div class="settings-card-header">연결 테스트</div>
  <div class="settings-row">
    <div>
      <div class="settings-label">API 서버 연결 상태</div>
      <div class="settings-desc" id="connTestResult">테스트를 실행해 주세요</div>
    </div>
    <button class="btn" style="width:auto;padding:8px 16px" onclick="testConn()">연결 테스트</button>
  </div>
</div>

<div style="display:flex;gap:8px;margin-top:4px">
  <button class="btn primary" style="width:auto;padding:9px 20px" onclick="saveSettings()">설정 저장</button>
  <button class="btn" style="width:auto;padding:9px 16px" onclick="loadSettings()">되돌리기</button>
</div>

<script>window.PAGE = 'settings';</script>
