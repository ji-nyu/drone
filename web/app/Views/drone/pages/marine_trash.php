<div class="marine-trash-page">

    <div class="page-section-title">
        <h2>해양쓰레기 위험 구역 지도</h2>
        <p>구역별 해양쓰레기 위험도와 미수거 현황을 확인합니다.</p>
    </div>

    <section class="marine-summary-grid">
        <div class="marine-summary-card">
            <span>총 탐지량</span>
            <strong id="totalDetectedCount">0</strong>
            <small>전체 탐지 쓰레기 개수</small>
        </div>

        <div class="marine-summary-card">
            <span>위험 구역</span>
            <strong id="dangerZoneCount" class="danger-text">0</strong>
            <small>위험 단계 구역 수</small>
        </div>

        <div class="marine-summary-card">
            <span>주의 구역</span>
            <strong id="warningZoneCount" class="warning-text">0</strong>
            <small>주의 단계 구역 수</small>
        </div>

        <div class="marine-summary-card">
            <span>정상 구역</span>
            <strong id="normalZoneCount">0</strong>
            <small>정상 단계 구역 수</small>
        </div>
    </section>

    <section class="marine-table-section">
        <div class="marine-table-header">
            <div class="marine-table-header-row">
                <h3>위험 구역 통계 요약</h3>
                <button type="button" id="btnGenerateReport" class="btn primary marine-report-btn">보고서 생성</button>
            </div>
            <p class="marine-note">※ 현재 통계는 플랫폼 기능 검증을 위한 예시 탐지 데이터를 사용합니다.</p>
            <p id="reportGenStatus" class="marine-report-status" aria-live="polite"></p>
        </div>

        <div id="topRiskSummary" class="marine-top-summary"></div>

        <div id="statsError" class="marine-error-card" style="display:none;">
            위험 구역 통계 데이터를 불러오지 못했습니다.
            JSON 파일 경로를 확인해주세요.
        </div>

        <div class="marine-table-wrapper">
            <table class="marine-table">
                <thead>
                    <tr>
                        <th>순위</th>
                        <th>구역명</th>
                        <th>탐지량</th>
                        <th>주요 쓰레기</th>
                        <th>미수거 시간</th>
                        <th>위험도</th>
                        <th>등급</th>
                        <th>상태</th>
                        <th>탐지 신뢰도</th>
                        <th>권장 조치</th>
                    </tr>
                </thead>
                <tbody id="statsTableBody">
                    <tr>
                        <td colspan="10">데이터를 불러오는 중입니다...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="marine-main-grid">

        <div class="marine-map-card">
            <div class="marine-card-header">
                <h3>위험 구역 지도</h3>

                <div class="risk-legend">
                    <span><i class="risk-dot low"></i>낮음</span>
                    <span><i class="risk-dot warning"></i>주의</span>
                    <span><i class="risk-dot danger"></i>위험</span>
                </div>
            </div>

            <div id="marineTrashMap"></div>
        </div>

        <aside class="marine-side-column">

            <div class="marine-panel-card">
                <div class="marine-card-header">
                    <h3>위험 구역 TOP 3</h3>
                </div>

                <div id="top3List" class="ranking-list">
                    <div class="ranking-empty">위험도 데이터가 없습니다.</div>
                </div>
            </div>

            <div class="marine-panel-card">
                <div class="marine-card-header">
                    <h3>자동 보고서</h3>
                </div>
                <div id="automaticReport" class="marine-report-card">
                    <p id="reportGeneratedAt" class="report-generated">보고서 생성일: 정보 없음</p>
                    <ul id="reportActions" class="report-actions-list">
                        <li>권장 조치가 없습니다.</li>
                    </ul>
                    <p id="reportNotes" class="report-notes"></p>
                </div>
            </div>

            <div class="marine-panel-card">
                <div class="marine-card-header">
                    <h3>경고·알림 목록</h3>
                </div>

                <div class="marine-alert danger-alert">
                    <strong>긴급 알림</strong>
                    <p>함덕 A구역이 고위험 단계로 변경되었습니다.</p>
                    <small>미수거 쓰레기 18개 · 수거 경로 생성 권장</small>
                </div>

                <div class="marine-alert warning-alert">
                    <strong>증가 알림</strong>
                    <p>함덕 C구역의 탐지량이 25% 증가했습니다.</p>
                    <small>추가 순찰을 권장합니다.</small>
                </div>
            </div>

        </aside>
    </section>

</div>
<style>
.marine-trash-page {
    padding: 20px;
}

.page-section-title {
    margin-bottom: 18px;
}

.page-section-title h2 {
    margin: 0 0 5px;
    font-size: 22px;
}

.page-section-title p {
    margin: 0;
    color: #7b8494;
    font-size: 13px;
}

.marine-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 16px;
}

.marine-summary-card {
    display: flex;
    flex-direction: column;
    min-height: 110px;
    padding: 18px;
    background: #ffffff;
    border: 1px solid #e7ebf0;
    border-radius: 12px;
}

.marine-summary-card span {
    color: #7b8494;
    font-size: 13px;
}

.marine-summary-card strong {
    margin-top: 8px;
    font-size: 28px;
}

.marine-summary-card small {
    margin-top: 3px;
    color: #9aa3af;
}

.marine-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 3fr) minmax(320px, 1fr);
    gap: 16px;
}

    .marine-table-section {
        margin-bottom: 20px;
    }

    .marine-table-header {
        margin-bottom: 10px;
    }

    .marine-table-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .marine-table-header-row h3 {
        margin: 0;
    }

    .marine-report-btn {
        flex-shrink: 0;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .marine-report-status {
        margin: 8px 0 0;
        font-size: 13px;
        color: #475569;
        min-height: 1.2em;
    }

    .marine-report-status.is-error {
        color: #dc2626;
    }

    .marine-report-status.is-ok {
        color: #15803d;
    }

    .marine-report-status a {
        color: #1d4ed8;
        font-weight: 600;
        text-decoration: underline;
    }

    .marine-table-wrapper {
        overflow-x: auto;
        background: #ffffff;
        border: 1px solid #e7ebf0;
        border-radius: 12px;
    }

    .marine-top-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }

    .marine-top-summary-item {
        padding: 10px 12px;
        border: 1px solid #e7ebf0;
        border-radius: 12px;
        background: #fbfcfe;
        font-size: 13px;
        color: #252f3f;
    }

    .marine-top-summary-item strong {
        display: block;
        margin-bottom: 4px;
        font-size: 14px;
        font-weight: 700;
    }

    .marine-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1020px;
    }

    .marine-table th,
    .marine-table td {
        padding: 12px 14px;
        border-bottom: 1px solid #edf0f3;
        vertical-align: top;
        font-size: 13px;
        color: #334155;
    }

    .marine-table th {
        background: #f8fafc;
        text-align: left;
        font-weight: 600;
        white-space: nowrap;
    }

    .marine-table tbody tr:last-child td {
        border-bottom: none;
    }

    .marine-table td .zone-id {
        display: block;
        margin-top: 4px;
        font-size: 12px;
        color: #7b8494;
    }

    .marine-table td .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 8px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1;
    }

    .marine-table tbody tr {
        cursor: pointer;
        transition: background-color 0.2s ease;
        border-left: 4px solid transparent;
    }

    .marine-table tbody tr:hover {
        background: rgba(15, 23, 42, 0.04);
    }

    .marine-table tbody tr.selected {
        background: rgba(59, 130, 246, 0.12);
    }

    .marine-table tbody tr[data-risk-level="danger"] {
        background: rgba(254, 226, 226, 0.4);
        border-left-color: #ef4444;
    }

    .marine-table tbody tr[data-risk-level="warning"] {
        background: rgba(254, 243, 199, 0.5);
        border-left-color: #facc15;
    }

    .marine-table tbody tr[data-risk-level="normal"] {
        background: rgba(220, 252, 231, 0.5);
        border-left-color: #22c55e;
    }

    .marine-note {
        margin: 6px 0 0;
        font-size: 12px;
        color: #6b7280;
    }

    .risk-badge {
        color: #ffffff;
        background: #94a3b8;
    }

    .status-badge {
        color: #1f2937;
        background: #f3f4f6;
    }

    .status-collected {
        background: #d1fae5;
        color: #166534;
    }

    .status-uncollected {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-unverified {
        background: #fef3c7;
        color: #92400e;
    }

    .marine-error-card {
        padding: 14px 16px;
        margin-bottom: 12px;
        border-radius: 12px;
        border: 1px solid #fecaca;
        background: #fff1f2;
        color: #991b1b;
    }


.marine-map-card {
    overflow: hidden;
}

.marine-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    padding: 16px 18px;
    border-bottom: 1px solid #edf0f3;
}

.marine-card-header h3 {
    margin: 0;
    font-size: 16px;
}

#marineTrashMap {
    height: 610px;
    background:
        linear-gradient(rgba(39, 170, 220, 0.12), rgba(39, 170, 220, 0.12)),
        #d9edf6;
}

.marine-side-column {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.risk-legend {
    display: flex;
    gap: 12px;
    font-size: 12px;
    flex-wrap: wrap;
}

.risk-legend span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.risk-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
}

.risk-dot.low {
    background: #22c55e;
}

.risk-dot.warning {
    background: #facc15;
}

.risk-dot.danger {
    background: #ef4444;
}

.ranking-list {
    padding: 8px 14px 14px;
}

.ranking-item {
    display: grid;
    grid-template-columns: 34px 1fr auto;
    align-items: center;
    width: 100%;
    padding: 14px 6px;
    background: transparent;
    border: 0;
    border-bottom: 1px solid #edf0f3;
    text-align: left;
    cursor: pointer;
}

.ranking-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    color: #ffffff;
    font-weight: 700;
}

.danger-rank {
    background: #ef4444;
}

.warning-rank {
    background: #f4b400;
}

.normal-rank {
    background: #22c55e;
}

.ranking-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.ranking-info small {
    color: #8a94a3;
}

.ranking-score {
    font-weight: 700;
}

.marine-report-card {
    padding: 14px 16px;
    border-top: 1px solid #edf0f3;
    background: #fbfcfe;
}

.report-generated {
    margin: 0 0 12px;
    font-size: 13px;
    color: #52606d;
}

.report-actions-list {
    margin: 0 0 12px;
    padding-left: 18px;
    color: #334155;
}

.report-actions-list li {
    margin-bottom: 8px;
    line-height: 1.5;
}

.report-notes {
    margin: 0;
    font-size: 13px;
    color: #606f7d;
}

.marine-alert {
    margin: 12px;
    padding: 14px;
    border-radius: 9px;
}

.marine-alert p {
    margin: 8px 0 4px;
    font-size: 13px;
}

.marine-alert small {
    font-size: 12px;
}

.danger-alert {
    background: #fff2f2;
    border: 1px solid #ffcaca;
}

.warning-alert {
    background: #fff9e8;
    border: 1px solid #ffe29a;
}

.danger-text {
    color: #ef4444;
}

.warning-text {
    color: #e6a700;
}

.success-text {
    color: #22a559;
}

@media (max-width: 1100px) {
    .marine-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .marine-main-grid {
        grid-template-columns: 1fr;
    }
}
</style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
    const mapConfig = {
        map_center: {
            latitude: 33.5455,
            longitude: 126.6698,
            zoom: 16
        }
    };

    const marineZonesData = <?= json_encode(
        $marineZones ?? [],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?>;

    const zoneRiskData = <?= json_encode(
        $zoneRiskSummary ?? [],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?>;

    const initMarineMap = () => {
        const initialCenter = (marineZonesData && marineZonesData.map_center) ? marineZonesData.map_center : mapConfig.map_center;

        const map = L.map('marineTrashMap', {
            zoomControl: true,
            scrollWheelZoom: true
        }).setView([initialCenter.latitude, initialCenter.longitude], initialCenter.zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        L.control.scale({ position: 'bottomleft' }).addTo(map);

        window.map = map;
        window.zoneLayers = window.zoneLayers || {};

        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        // fixedZoneLayer: polygons loaded from server-side JSON (app/Data/*.json)
        const fixedZoneLayer = new L.FeatureGroup();
        map.addLayer(fixedZoneLayer);

        const riskByZoneId = new Map((zoneRiskData.zones ?? []).map(item => [item.zone_id, item]));

        // track fixed zone ids to avoid duplicating in drawItems
        const fixedZoneIds = new Set();

        const translateRiskLevel = (level) => {
            if (!level) return '정보 없음';
            if (level === 'danger') return '위험';
            if (level === 'warning') return '주의';
            if (level === 'normal') return '정상';
            return '정보 없음';
        };

        const getRiskLevelClass = (level) => {
            if (level === 'danger') return 'danger';
            if (level === 'warning') return 'warning';
            if (level === 'normal') return 'normal';
            return '';
        };

        const renderRiskSummaryCards = (summary) => {
            const data = summary ?? {};

            document.getElementById('totalDetectedCount').textContent = data.total_detected_count ?? 0;
            document.getElementById('dangerZoneCount').textContent = data.danger_zone_count ?? 0;
            document.getElementById('warningZoneCount').textContent = data.warning_zone_count ?? 0;
            document.getElementById('normalZoneCount').textContent = data.normal_zone_count ?? 0;
        };

        const updateAutomaticReport = () => {
            const summary = zoneRiskData.report_summary ?? {};
            const generatedAt = zoneRiskData.generated_at || summary.generated_at || null;
            const actions = Array.isArray(summary.recommended_actions) ? summary.recommended_actions : [];
            const notes = summary.notes || summary.comment || zoneRiskData.report_notes || '';

            const generatedAtEl = document.getElementById('reportGeneratedAt');
            const reportActionsEl = document.getElementById('reportActions');
            const reportNotesEl = document.getElementById('reportNotes');

            if (generatedAtEl) {
                generatedAtEl.textContent = generatedAt
                    ? `보고서 생성일: ${generatedAt}`
                    : '보고서 생성일: 정보 없음';
            }

            if (reportActionsEl) {
                reportActionsEl.innerHTML = actions.length > 0
                    ? actions.map((action) => `<li>${action}</li>`).join('')
                    : '<li>권장 조치가 없습니다.</li>';
            }

            if (reportNotesEl) {
                reportNotesEl.textContent = notes || '추가 보고서 요약이 없습니다.';
            }
        };

        const renderTop3List = () => {
            const top3List = document.getElementById('top3List');
            if (!top3List) return;

            const top3 = Array.isArray(zoneRiskData.top3) && zoneRiskData.top3.length > 0
                ? zoneRiskData.top3
                : [...(zoneRiskData.zones ?? [])]
                    .sort((a, b) => (b.risk_score ?? 0) - (a.risk_score ?? 0))
                    .slice(0, 3);

            if (!Array.isArray(top3) || top3.length === 0) {
                top3List.innerHTML = '<div class="ranking-empty">위험도 데이터가 없습니다.</div>';
                return;
            }

            top3List.innerHTML = top3.map((item, index) => {
                const rank = index + 1;
                const zoneId = item.zone_id ?? '정보 없음';
                const riskScore = item.risk_score ?? 0;
                const levelText = translateRiskLevel(item.risk_level);
                const trashCount = item.trash_count ?? 0;
                const mainTrashType = item.main_trash_type ?? '정보 없음';
                const levelClass = getRiskLevelClass(item.risk_level);

                return `
                    <button type="button" class="ranking-item ${levelClass}" data-zone="${zoneId}">
                        <span class="ranking-number ${levelClass}-rank">${rank}위</span>
                        <span class="ranking-info">
                            <strong>${zoneId}</strong>
                            <small>탐지량 ${trashCount}개 · ${mainTrashType}</small>
                        </span>
                        <span class="ranking-score ${levelClass}-text">${riskScore}점</span>
                    </button>
                `;
            }).join('');
        };

        const riskLevelLabels = {
            danger: '위험',
            warning: '주의',
            normal: '정상'
        };

        const statusLabels = {
            collected: '수거 완료',
            uncollected: '미수거',
            unverified: '미확인'
        };

        const defaultRiskColor = {
            danger: '#ef4444',
            warning: '#facc15',
            normal: '#22c55e'
        };

        const formatUncollectedHours = (value) => {
            if (value == null || value === 0) {
                return '-';
            }
            const num = Number(value);
            if (Number.isNaN(num)) {
                return '-';
            }
            if (Number.isInteger(num)) {
                return `${num}시간`;
            }
            return `${Math.round(num * 10) / 10}시간`;
        };

        const formatConfidence = (value) => {
            if (value == null || value === '' || Number.isNaN(Number(value))) {
                return '-';
            }
            return `${Math.round(Number(value) * 100)}%`;
        };

        const mergeZoneData = (fixedData, detectionData, riskData) => {
            const detectionsByZone = new Map((detectionData.detections || []).map(item => [item.zone_id, item]));

            return (riskData.zones || []).map((risk) => {
                const fixedZone = (fixedData.zones || []).find(zone => zone.zone_id === risk.zone_id);
                const detection = detectionsByZone.get(risk.zone_id);

                return {
                    zone_id: risk.zone_id,
                    zone_name: fixedZone?.zone_name ?? risk.zone_id,
                    beach_name: fixedZone?.beach_name ?? '-',
                    latitude: fixedZone?.center?.latitude ?? null,
                    longitude: fixedZone?.center?.longitude ?? null,
                    polygon_geojson: fixedZone?.polygon_geojson ?? null,
                    trash_count: risk.trash_count ?? 0,
                    main_trash_type: risk.main_trash_type ?? '-',
                    status: detection?.status ?? risk.status ?? '-',
                    detected_at: detection?.detected_at ?? risk.detected_at ?? null,
                    uncollected_hours: risk.uncollected_hours ?? 0,
                    average_confidence: detection?.average_confidence ?? null,
                    risk_score: risk.risk_score ?? 0,
                    risk_level: risk.risk_level ?? 'normal',
                    map_color: risk.map_color ?? defaultRiskColor[risk.risk_level] ?? '#94a3b8',
                    recommended_action: risk.recommended_action ?? '-'
                };
            }).sort((a, b) => (b.risk_score ?? 0) - (a.risk_score ?? 0));
        };

        const getRiskBadge = (score, color) => {
            const badgeColor = color || defaultRiskColor.danger;
            return `<span class="badge risk-badge" style="background:${badgeColor}">${score}점</span>`;
        };

        const getStatusBadge = (status) => {
            const label = statusLabels[status] ?? status ?? '-';
            const className = status === 'collected' ? 'status-collected' : status === 'uncollected' ? 'status-uncollected' : 'status-unverified';
            return `<span class="badge status-badge ${className}">${label}</span>`;
        };

        const getZoneSummaryUrl = () => {
            return '/drone/marine-trash-data';
        };

        const renderTopRiskZones = (top3, mergedZones) => {
            const topSummary = document.getElementById('topRiskSummary');
            if (!topSummary) return;

            const zoneMap = new Map((mergedZones || []).map((zone) => [zone.zone_id, zone]));
            const items = (Array.isArray(top3) ? top3 : [])
                .slice(0, 3)
                .map((item, index) => {
                    const merged = zoneMap.get(item.zone_id) || {};
                    const label = merged.zone_name || item.zone_id || '정보 없음';
                    const score = item.risk_score ?? merged.risk_score ?? 0;
                    return `
                        <div class="marine-top-summary-item">
                            <strong>${index + 1}위 ${label}</strong>
                            위험도 ${score}점
                        </div>
                    `;
                });

            topSummary.innerHTML = items.length > 0 ? items.join('') : '<div class="marine-top-summary-item">상위 위험 구역 정보가 없습니다.</div>';
        };

        const focusZoneOnMap = (zone, latitude, longitude) => {
            if (!window.map || !zone) return;
            if (latitude && longitude) {
                window.map.setView([latitude, longitude], 15);
            }

            if (!zone.zone_id || !window.zoneLayers) return;
            const targetLayer = window.zoneLayers[zone.zone_id];
            if (targetLayer) {
                if (typeof targetLayer.openPopup === 'function') {
                    targetLayer.openPopup();
                }
                if (typeof targetLayer.setStyle === 'function') {
                    targetLayer.setStyle({ weight: 4 });
                    setTimeout(() => {
                        targetLayer.setStyle({ weight: 2 });
                    }, 2000);
                }
            }
        };

        const loadZoneStatsSummary = async () => {
            const statsError = document.getElementById('statsError');
            const statsTableBody = document.getElementById('statsTableBody');
            const url = getZoneSummaryUrl();

            try {
                const response = await fetch(url, { cache: 'no-store' });
                if (!response.ok) {
                    throw new Error('JSON 데이터를 불러오지 못했습니다.');
                }

                const data = await response.json();
                const fixedData = data.fixed ?? { zones: [] };
                const detectionData = data.detection ?? { detections: [] };
                const riskData = data.risk ?? { zones: [] };

                const mergedZones = mergeZoneData(fixedData, detectionData, riskData);
                renderTopRiskZones(riskData.top3, mergedZones);
                renderRiskSummaryCards(riskData.report_summary ?? {});

                if (!statsError || !statsTableBody) {
                    return;
                }

                if (mergedZones.length === 0) {
                    statsTableBody.innerHTML = '<tr><td colspan="10">데이터가 없습니다.</td></tr>';
                    statsError.style.display = 'none';
                    return;
                }

                statsError.style.display = 'none';
                statsTableBody.innerHTML = mergedZones.map((zone, index) => {
                    const rank = index + 1;
                    const zoneName = zone.zone_name || zone.zone_id;
                    const zoneId = zone.zone_id || '-';
                    const trashCountText = `${zone.trash_count ?? 0}개`;
                    const mainTrash = zone.main_trash_type || '-';
                    const uncollected = formatUncollectedHours(zone.uncollected_hours);
                    const riskScoreText = getRiskBadge(zone.risk_score ?? 0, zone.map_color);
                    const levelText = `<span class="badge risk-badge" style="background:${zone.map_color}">${riskLevelLabels[zone.risk_level] ?? '정보 없음'}</span>`;
                    const statusText = getStatusBadge(zone.status);
                    const confidenceText = formatConfidence(zone.average_confidence);
                    const recommended = zone.recommended_action || '-';

                    return `
                        <tr data-zone-id="${zoneId}" data-risk-level="${zone.risk_level}" data-latitude="${zone.latitude ?? ''}" data-longitude="${zone.longitude ?? ''}">
                            <td>${rank}</td>
                            <td><strong>${zoneName}</strong><span class="zone-id">${zoneId}</span></td>
                            <td>${trashCountText}</td>
                            <td>${mainTrash}</td>
                            <td>${uncollected}</td>
                            <td>${riskScoreText}</td>
                            <td>${levelText}</td>
                            <td>${statusText}</td>
                            <td>${confidenceText}</td>
                            <td>${recommended}</td>
                        </tr>
                    `;
                }).join('');

                const rows = statsTableBody.querySelectorAll('tr[data-zone-id]');
                const selectStatsRow = (selectedRow) => {
                    rows.forEach((r) => r.classList.remove('selected'));
                    selectedRow.classList.add('selected');
                };

                rows.forEach((row) => {
                    row.addEventListener('click', () => {
                        selectStatsRow(row);
                        const zoneId = row.dataset.zoneId;
                        const latitude = Number(row.dataset.latitude);
                        const longitude = Number(row.dataset.longitude);
                        const zone = mergedZones.find(item => item.zone_id === zoneId);
                        focusZoneOnMap(zone, latitude, longitude);
                    });
                });
            } catch (error) {
                console.error('위험 구역 통계 데이터를 불러오는 중 오류가 발생했습니다.', error);
                const statsError = document.getElementById('statsError');
                const statsTableBody = document.getElementById('statsTableBody');
                if (statsError) {
                    statsError.style.display = 'block';
                }
                if (statsTableBody) {
                    statsTableBody.innerHTML = '<tr><td colspan="10">위험 구역 통계 데이터를 불러오지 못했습니다.</td></tr>';
                }
            }
        };

        const renderFixedZonesFromJson = () => {
            fixedZoneLayer.clearLayers();

            const zones = (marineZonesData.zones || []).slice(0, 5);
            fixedZoneIds.clear();

            zones.forEach((zone) => {
                let geo = zone.polygon_geojson ?? zone.geojson ?? null;
                if (!geo) return;

                try {
                    if (typeof geo === 'string') {
                        geo = JSON.parse(geo);
                    }
                } catch (e) {
                    console.error('고정 폴리곤 파싱 실패', e);
                    return;
                }

                const risk = riskByZoneId.get(zone.zone_id);
                const color = (risk && risk.map_color) ? risk.map_color : '#94a3b8';

                const layer = L.geoJSON(geo, {
                    style: {
                        color: color,
                        fillColor: color,
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.25
                    }
                });

                const score = risk?.score ?? risk?.risk ?? '정보 없음';
                const levelText = translateRiskLevel(risk?.level ?? zone.level ?? null);
                const detections = risk?.count ?? zone.count ?? '정보 없음';
                const mainType = (risk && risk.major_type) || (zone.major_type) || '정보 없음';
                const uncollected = (risk && risk.uncollected_hours) || (zone.uncollected_hours) || '정보 없음';
                const action = (risk && risk.recommendation) || (zone.recommendation) || '정보 없음';

                const popupHtml = `
                    <div style="font-weight:700;margin-bottom:6px;">${zone.zone_name || '구역명 없음'}</div>
                    <div>구역 ID: ${zone.zone_id || 'N/A'}</div>
                    <div>위험도: ${score}점</div>
                    <div>등급: ${levelText}</div>
                    <div>탐지량: ${detections}</div>
                    <div>주요 종류: ${mainType}</div>
                    <div>미수거: ${uncollected}</div>
                    <div>조치: ${action}</div>
                `;

                layer.bindPopup(popupHtml);
                fixedZoneLayer.addLayer(layer);

                if (zone.zone_id) {
                    fixedZoneIds.add(zone.zone_id);
                    window.zoneLayers[zone.zone_id] = layer;
                }
            });

            // Fit bounds to fixed zones if any
            try {
                const bounds = fixedZoneLayer.getBounds();
                if (bounds.isValid && fixedZoneLayer.getLayers().length > 0) {
                    map.fitBounds(bounds.pad(0.1));
                }
            } catch (e) {
                // ignore
            }
        };

        const renderSavedZones = (items) => {
            // items: [{ feature: GeoJSONFeature, meta: zoneRow }, ...]
            drawnItems.clearLayers();
            (items || []).forEach((item) => {
                const feature = item.feature;
                const meta = item.meta || {};

                // skip if this zone is one of the fixed JSON zones
                if (meta.zone_id && fixedZoneIds.has(meta.zone_id)) {
                    return;
                }

                const risk = riskByZoneId.get(meta.zone_id) || {};
                const color = risk.map_color || '#94a3b8';

                const layer = L.geoJSON(feature, {
                    style: {
                        color: color,
                        fillColor: color,
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.25
                    }
                });

                const score = risk?.score ?? meta.risk ?? '정보 없음';
                const levelText = translateRiskLevel(risk?.level ?? meta.level ?? null);
                const detections = risk?.count ?? meta.count ?? '정보 없음';
                const mainType = (risk && risk.major_type) || (meta.major_type) || '정보 없음';
                const uncollected = (risk && risk.uncollected_hours) || (meta.uncollected_hours) || '정보 없음';
                const action = (risk && risk.recommendation) || (meta.recommendation) || '정보 없음';

                const popupHtml = `
                    <div style="font-weight:700;margin-bottom:6px;">${meta.zone_name || '구역명 없음'}</div>
                    <div>구역 ID: ${meta.zone_id || 'N/A'}</div>
                    <div>위험도: ${score}점</div>
                    <div>등급: ${levelText}</div>
                    <div>탐지량: ${detections}</div>
                    <div>주요 종류: ${mainType}</div>
                    <div>미수거: ${uncollected}</div>
                    <div>조치: ${action}</div>
                `;

                layer.bindPopup(popupHtml);
                drawnItems.addLayer(layer);
            });
        };

        const loadSavedDrawings = async () => {
            drawnItems.clearLayers();

            try {
                const response = await fetch('/api/marine-zones');
                if (!response.ok) {
                    throw new Error(`서버 응답 오류 ${response.status}`);
                }

                const result = await response.json();
                const serverZones = result.data || [];
                const items = [];

                if (Array.isArray(serverZones) && serverZones.length > 0) {
                    serverZones.forEach((zone) => {
                        try {
                            const geojson = JSON.parse(zone.polygon_geojson || '{}');
                            if (geojson && (geojson.type === 'Feature' || geojson.type === 'FeatureCollection' || geojson.type === 'Polygon' || geojson.type === 'MultiPolygon')) {
                                items.push({ feature: geojson, meta: zone });
                            }
                        } catch (error) {
                            console.error('저장된 구역 파싱 실패', error);
                        }
                    });
                }

                if (items.length > 0) {
                    renderSavedZones(items);
                }
            } catch (error) {
                console.error('서버 구역 불러오기 실패', error);
            }
        };



        const bindReportGenerateButton = () => {
            const btn = document.getElementById('btnGenerateReport');
            const statusEl = document.getElementById('reportGenStatus');
            if (!btn) return;

            const setStatus = (text, kind) => {
                if (!statusEl) return;
                statusEl.classList.remove('is-error', 'is-ok');
                if (kind) statusEl.classList.add(kind);
                statusEl.innerHTML = text;
            };

            btn.addEventListener('click', async () => {
                if (btn.disabled) return;
                btn.disabled = true;
                setStatus('보고서를 생성 중입니다. LLM 추론에 수 분이 걸릴 수 있습니다…', null);

                try {
                    const response = await fetch('/api/reports/generate', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({}),
                    });
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok || !result.ok) {
                        const detail = result.detail || result.message || `HTTP ${response.status}`;
                        setStatus(`생성 실패: ${detail}`, 'is-error');
                        return;
                    }

                    const href = result.download_url || '';
                    const name = result.filename || '보고서.docx';
                    const mission = result.mission_id ? ` (${result.mission_id})` : '';
                    setStatus(
                        `생성 완료${mission}. <a href="${href}" download="${name}">${name} 다운로드</a>`,
                        'is-ok'
                    );

                    if (href) {
                        // 자동 다운로드 시도
                        const a = document.createElement('a');
                        a.href = href;
                        a.download = name;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                    }
                } catch (error) {
                    console.error(error);
                    setStatus(`생성 실패: ${error.message || error}`, 'is-error');
                } finally {
                    btn.disabled = false;
                }
            });
        };

        renderRiskSummaryCards(zoneRiskData.report_summary ?? {});
        updateAutomaticReport();
        renderTop3List();
        renderFixedZonesFromJson();
        loadZoneStatsSummary();
        bindReportGenerateButton();
        // Note: do not auto-load DB-saved polygons on page load to avoid
        // duplicate rendering with fixed JSON polygons.
        // loadSavedDrawings();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMarineMap);
    } else {
        initMarineMap();
    }
})();
</script>