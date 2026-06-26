<!-- Missions page — flex-content layout -->
<div class="missions-left">
  <div class="ctrl-topbar" style="justify-content:space-between">
    <span style="font-size:13px;font-weight:600;color:#111827">임무 현황</span>
    <div style="display:flex;gap:6px;align-items:center">
      <select id="missionDroneFilter" style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:3px 8px;font-size:12px;color:#374151;outline:none" onchange="refreshMissions()">
        <option value="">전체 드론</option>
      </select>
      <button class="btn-xs" onclick="refreshMissions()">새로고침</button>
      <button class="btn primary" style="font-size:11px;padding:5px 12px" onclick="showCreateForm()">+ 새 미션</button>
    </div>
  </div>
  <div style="flex:1;overflow-y:auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>임무 ID</th><th>기체</th><th>상태</th><th>액션 수</th><th>시작</th><th>완료</th>
        </tr>
      </thead>
      <tbody id="missionBody">
        <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:40px 0">임무 데이터 없음</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="missions-right">

  <!-- ── 빈 상태 ── -->
  <div id="mPanelEmpty" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#9ca3af;padding:40px 20px;text-align:center">
    <div style="font-size:36px;margin-bottom:12px;opacity:.2">≡</div>
    <div style="font-size:13px;font-weight:500;margin-bottom:4px">임무를 선택하거나</div>
    <div style="font-size:12px">새 미션을 작성하세요</div>
  </div>

  <!-- ── 미션 생성 폼 ── -->
  <div id="mPanelCreate" style="display:none;flex-direction:column;height:100%">
    <div class="ctrl-topbar" style="justify-content:space-between;flex-shrink:0">
      <span style="font-size:12px;font-weight:600;color:#111827">새 미션 작성</span>
      <button class="btn-xs" onclick="showEmptyPanel()">✕ 닫기</button>
    </div>

    <div style="flex:1;overflow-y:auto">

      <!-- 드론 선택 + 배터리 거리 추정 -->
      <div class="mpanel-section">
        <div class="mpanel-title">드론 선택</div>
        <select class="drone-select" id="mDroneSelect" onchange="onDroneSelect(this.value)" style="width:100%;padding-right:28px"></select>
        <div id="mRangeInfo" style="margin-top:10px;display:none">
          <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:5px">
            <span style="color:#6b7280">배터리 <strong id="mBatPct" style="color:#111827">--%</strong></span>
            <span style="color:#6b7280">예상 편도 <strong id="mRangeDist" style="color:#1d4ed8">-- m</strong></span>
          </div>
          <div class="range-bar"><div class="range-fill" id="mRangeFill" style="width:0%"></div></div>
          <div id="mRangeWarn" style="font-size:11px;color:#b45309;margin-top:5px;display:none">⚠ 배터리 30% 미만 — 비행을 권장하지 않습니다</div>
        </div>
      </div>

      <!-- 비행 액션 시퀀스 -->
      <div class="mpanel-section">
        <div class="mpanel-title" style="margin-bottom:8px">비행 액션 시퀀스</div>

        <!-- 입력 방식 탭 -->
        <div style="display:flex;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;margin-bottom:10px">
          <button id="mModeManualBtn" onclick="switchActionMode('manual')"
            style="flex:1;padding:5px 0;font-size:11px;font-weight:600;border:none;cursor:pointer;background:#1d4ed8;color:#fff">
            ✎ 수동 작성
          </button>
          <button id="mModeJsBtn" onclick="switchActionMode('js')"
            style="flex:1;padding:5px 0;font-size:11px;font-weight:600;border:none;cursor:pointer;background:#f3f4f6;color:#6b7280">
            { } JSON 붙여넣기
          </button>
        </div>

        <!-- 수동 작성 영역 -->
        <div id="mManualArea">
          <div id="mActionEmpty" style="font-size:12px;color:#9ca3af;padding:4px 0 8px">
            액션을 추가하세요 (이륙으로 시작, 착륙으로 마무리 권장)
          </div>
          <div id="mActionList"></div>
          <div style="margin-top:8px;display:flex;gap:4px;flex-wrap:wrap">
            <button class="btn-xs" onclick="addAction()">+ 액션 추가</button>
            <button class="btn-xs" onclick="addPreset('takeoff')">이륙</button>
            <button class="btn-xs" onclick="addPreset('forward')">앞으로</button>
            <button class="btn-xs" onclick="addPreset('deliver')">배송투하</button>
            <button class="btn-xs" onclick="addPreset('land')">착륙</button>
          </div>
        </div>

        <!-- AI 붙여넣기 영역 -->
        <div id="mJsArea" style="display:none">
          <p style="font-size:11px;color:#6b7280;margin:0 0 6px">
            JSON 배열을 붙여넣으세요.<br>
            예: <code style="font-size:10px">[{"type":"takeoff"},{"type":"forward","value":200},{"type":"land"}]</code>
          </p>
          <textarea id="mJsInput"
            style="width:100%;height:110px;font-size:11px;font-family:monospace;padding:8px;border:1px solid #d1d5db;border-radius:6px;resize:vertical;outline:none;box-sizing:border-box"
            placeholder='[&#10;  { "type": "takeoff" },&#10;  { "type": "forward", "value": 200 },&#10;  { "type": "deliver" },&#10;  { "type": "land" }&#10;]'></textarea>
          <div id="mJsError" style="font-size:11px;color:#dc2626;margin:4px 0;display:none"></div>
          <button class="btn primary" onclick="parseActionJs()" style="width:100%;margin-top:6px">파싱 후 적용</button>
        </div>
      </div>

      <!-- 이륙 전 체크리스트 -->
      <div class="mpanel-section">
        <div class="mpanel-title">이륙 전 체크리스트</div>
        <div id="mChecklist"></div>
      </div>

      <!-- 제출 -->
      <div class="mpanel-section" style="border-bottom:none">
        <button class="btn primary" id="mSubmitBtn" onclick="submitMission()" style="width:100%" disabled>임시 저장</button>
        <p id="mSubmitHint" style="font-size:11px;color:#9ca3af;text-align:center;margin-top:7px">체크리스트를 모두 완료하면 활성화됩니다</p>
      </div>
    </div>
  </div>

  <!-- ── 미션 상세 ── -->
  <div id="mPanelDetail" style="display:none;flex-direction:column;height:100%">
    <div class="ctrl-topbar" style="justify-content:space-between;flex-shrink:0">
      <span style="font-size:12px;font-weight:600;color:#111827" id="mDetailTitle">임무 상세</span>
      <div style="display:flex;gap:6px">
        <button class="btn-xs" style="color:#dc2626;border-color:#fca5a5" onclick="deleteMission()">삭제</button>
        <button class="btn-xs" onclick="showEmptyPanel()">✕ 닫기</button>
      </div>
    </div>

    <div style="flex:1;overflow-y:auto">

      <!-- 기본 정보 -->
      <div class="mpanel-section">
        <div class="mpanel-title">임무 정보</div>
        <div class="info-grid">
          <div class="info-cell"><div class="info-cell-label">임무 ID</div><div class="info-cell-value muted" id="dMissionId">—</div></div>
          <div class="info-cell"><div class="info-cell-label">기체</div><div class="info-cell-value" id="dDroneId">—</div></div>
          <div class="info-cell"><div class="info-cell-label">시작</div><div class="info-cell-value muted" id="dStartedAt">—</div></div>
          <div class="info-cell"><div class="info-cell-label">완료</div><div class="info-cell-value muted" id="dEndedAt">—</div></div>
        </div>
      </div>

      <!-- 이벤트 타임라인 + 배송완료 서버 반영 확인 -->
      <div class="mpanel-section" style="display:none">
        <div class="mpanel-title">이벤트 타임라인</div>
        <div id="dTimeline"><div style="font-size:12px;color:#9ca3af">로딩 중...</div></div>
      </div>

      <!-- 액션 실행 결과 -->
      <div class="mpanel-section" id="dActionSection" style="display:none">
        <div class="mpanel-title">액션 실행 결과</div>
        <div id="dActionList"></div>
      </div>

      <!-- 임무 시작 (draft 상태) -->
      <div class="mpanel-section" id="dStartSection" style="display:none">
        <button id="dRunBtn" class="btn primary full" onclick="startMission()">▶ 임무 실행</button>
      </div>

      <!-- 배송 확인 -->
      <div class="mpanel-section" id="dConfirmSection" style="display:none">
        <button class="btn primary full" onclick="confirmDelivery()">✓ 배송 완료 수동 확인</button>
      </div>

      <!-- 실패 재시도 -->
      <div class="mpanel-section" id="dRetrySection" style="display:none;border-bottom:none">
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 14px;margin-bottom:12px">
          <div style="font-size:12px;font-weight:600;color:#dc2626;margin-bottom:3px">미션 실패</div>
          <div style="font-size:11px;color:#9ca3af" id="dFailReason">—</div>
        </div>
        <button class="btn danger full" onclick="retryMission()">↺ 동일 액션으로 재시도</button>
      </div>

    </div>
  </div>

</div>

<script>window.PAGE = 'missions';</script>
