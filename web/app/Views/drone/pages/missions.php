<!-- Missions — Waveon -->
<div class="missions-left">
  <div class="ctrl-topbar missions-toolbar">
    <span class="missions-toolbar-title">임무 목록</span>
    <div class="missions-toolbar-actions">
      <select id="missionDroneFilter" class="drone-select mission-filter" onchange="refreshMissions()">
        <option value="">전체 드론</option>
      </select>
      <button type="button" class="btn" onclick="refreshMissions()">새로고침</button>
      <button type="button" class="btn primary" onclick="showCreateForm()">+ 새 미션</button>
    </div>
  </div>
  <div class="missions-table-wrap">
    <table class="data-table missions-table">
      <thead>
        <tr>
          <th>임무 ID</th><th>기체</th><th>상태</th><th>액션 수</th><th>시작</th><th>완료</th>
        </tr>
      </thead>
      <tbody id="missionBody">
        <tr><td colspan="6" class="missions-empty-row">임무 데이터 없음</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="missions-right">

  <div id="mPanelEmpty" class="mission-panel-empty">
    <div class="mission-empty-icon" aria-hidden="true">
      <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="10" y="6" width="28" height="36" rx="3"/>
        <path d="M17 16h14M17 24h14M17 32h8"/>
      </svg>
    </div>
    <div class="mission-empty-title">임무를 선택하거나</div>
    <div class="mission-empty-sub">새 미션을 작성하세요</div>
  </div>

  <div id="mPanelCreate" class="mission-panel" style="display:none">
    <div class="ctrl-topbar missions-panel-bar">
      <span class="missions-toolbar-title">새 미션 작성</span>
      <button type="button" class="btn-xs" onclick="showEmptyPanel()">✕ 닫기</button>
    </div>

    <div class="mission-panel-body">
      <div class="mpanel-section">
        <div class="mpanel-title">드론 선택</div>
        <select class="drone-select" id="mDroneSelect" onchange="onDroneSelect(this.value)" style="width:100%;padding-right:28px"></select>
        <div id="mRangeInfo" class="m-range-info" style="display:none">
          <div class="m-range-meta">
            <span>배터리 <strong id="mBatPct">--%</strong></span>
            <span>예상 편도 <strong id="mRangeDist" class="accent">-- m</strong></span>
          </div>
          <div class="range-bar"><div class="range-fill" id="mRangeFill" style="width:0%"></div></div>
          <div id="mRangeWarn" class="m-range-warn" style="display:none">⚠ 배터리 30% 미만 — 비행을 권장하지 않습니다</div>
        </div>
      </div>

      <div class="mpanel-section">
        <div class="mpanel-title">비행 액션 시퀀스</div>

        <div class="mode-tabs">
          <button type="button" id="mModeManualBtn" class="mode-tab active" onclick="switchActionMode('manual')">✎ 수동 작성</button>
          <button type="button" id="mModeJsBtn" class="mode-tab" onclick="switchActionMode('js')">{ } JSON 붙여넣기</button>
        </div>

        <div id="mManualArea">
          <div id="mActionEmpty" class="m-action-hint">
            액션을 추가하세요 (이륙으로 시작, 착륙으로 마무리 권장)
          </div>
          <div id="mActionList"></div>
          <div class="m-action-presets">
            <button type="button" class="btn-xs" onclick="addAction()">+ 액션 추가</button>
            <button type="button" class="btn-xs" onclick="addPreset('takeoff')">이륙</button>
            <button type="button" class="btn-xs" onclick="addPreset('forward')">앞으로</button>
            <button type="button" class="btn-xs" onclick="addPreset('deliver')">배송투하</button>
            <button type="button" class="btn-xs" onclick="addPreset('land')">착륙</button>
          </div>
        </div>

        <div id="mJsArea" style="display:none">
          <p class="m-js-hint">
            JSON 배열을 붙여넣으세요.<br>
            예: <code>[{"type":"takeoff"},{"type":"forward","value":200},{"type":"land"}]</code>
          </p>
          <textarea id="mJsInput" class="m-js-input"
            placeholder='[&#10;  { "type": "takeoff" },&#10;  { "type": "forward", "value": 200 },&#10;  { "type": "deliver" },&#10;  { "type": "land" }&#10;]'></textarea>
          <div id="mJsError" class="m-js-error" style="display:none"></div>
          <button type="button" class="btn primary full" onclick="parseActionJs()" style="margin-top:8px">파싱 후 적용</button>
        </div>
      </div>

      <div class="mpanel-section">
        <div class="mpanel-title">이륙 전 체크리스트</div>
        <div id="mChecklist"></div>
      </div>

      <div class="mpanel-section" style="border-bottom:none">
        <button type="button" class="btn primary full" id="mSubmitBtn" onclick="submitMission()" disabled>임시 저장</button>
        <p id="mSubmitHint" class="m-submit-hint">체크리스트를 모두 완료하면 활성화됩니다</p>
      </div>
    </div>
  </div>

  <div id="mPanelDetail" class="mission-panel" style="display:none">
    <div class="ctrl-topbar missions-panel-bar">
      <span class="missions-toolbar-title" id="mDetailTitle">임무 상세</span>
      <div class="missions-toolbar-actions">
        <button type="button" class="btn-xs danger-xs" onclick="deleteMission()">삭제</button>
        <button type="button" class="btn-xs" onclick="showEmptyPanel()">✕ 닫기</button>
      </div>
    </div>

    <div class="mission-panel-body">
      <div class="mpanel-section">
        <div class="mpanel-title">임무 정보</div>
        <div class="info-grid">
          <div class="info-cell"><div class="info-cell-label">임무 ID</div><div class="info-cell-value muted" id="dMissionId">—</div></div>
          <div class="info-cell"><div class="info-cell-label">기체</div><div class="info-cell-value" id="dDroneId">—</div></div>
          <div class="info-cell"><div class="info-cell-label">시작</div><div class="info-cell-value muted" id="dStartedAt">—</div></div>
          <div class="info-cell"><div class="info-cell-label">완료</div><div class="info-cell-value muted" id="dEndedAt">—</div></div>
        </div>
      </div>

      <div class="mpanel-section" style="display:none">
        <div class="mpanel-title">이벤트 타임라인</div>
        <div id="dTimeline"><div class="m-action-hint">로딩 중...</div></div>
      </div>

      <div class="mpanel-section" id="dActionSection" style="display:none">
        <div class="mpanel-title">액션 실행 결과</div>
        <div id="dActionList"></div>
      </div>

      <div class="mpanel-section" id="dStartSection" style="display:none">
        <button type="button" id="dRunBtn" class="btn primary full" onclick="startMission()">▶ 임무 실행</button>
      </div>

      <div class="mpanel-section" id="dConfirmSection" style="display:none">
        <button type="button" class="btn primary full" onclick="confirmDelivery()">✓ 배송 완료 수동 확인</button>
      </div>

      <div class="mpanel-section" id="dRetrySection" style="display:none;border-bottom:none">
        <div class="m-fail-box">
          <div class="m-fail-title">미션 실패</div>
          <div class="m-fail-reason" id="dFailReason">—</div>
        </div>
        <button type="button" class="btn danger full" onclick="retryMission()">↺ 동일 액션으로 재시도</button>
      </div>
    </div>
  </div>

</div>

<script>window.PAGE = 'missions';</script>
