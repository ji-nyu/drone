<?php
/**
 * recommendation_helper.php
 * Utility to compute recommended_action for zones based on status and risk_level.
 */

if (! function_exists('getRecommendedAction')) {
    function getRecommendedAction(?string $status, ?string $riskLevel): string
    {
        $s = is_string($status) ? trim(strtolower($status)) : '';
        $r = is_string($riskLevel) ? trim(strtolower($riskLevel)) : '';

        if ($s === 'collecting') {
            return '수거 작업 진행 상황 모니터링';
        }

        if ($s === 'collected') {
            return '수거 완료 구역 재점검 및 상태 확인';
        }

        if ($s === 'unverified') {
            return '현장 확인 후 수거 여부 결정';
        }

        if ($s === 'uncollected') {
            if ($r === 'danger') {
                return '즉시 현장 확인 및 우선 수거';
            }
            if ($r === 'warning') {
                return '24시간 이내 현장 확인 및 수거 계획 수립';
            }
            return '정기 모니터링 유지';
        }

        // unexpected status: fallback to risk_level
        if ($r === 'danger') {
            return '즉시 현장 확인 및 우선 수거';
        }
        if ($r === 'warning') {
            return '24시간 이내 현장 확인 및 수거 계획 수립';
        }
        return '정기 모니터링 유지';
    }
}

if (! function_exists('recomputeZoneRecommendedActions')) {
    function recomputeZoneRecommendedActions(array &$zoneRiskSummary): void
    {
        if (! isset($zoneRiskSummary['zones']) || ! is_array($zoneRiskSummary['zones'])) {
            return;
        }

        // recompute per-zone recommended_action
        foreach ($zoneRiskSummary['zones'] as &$zone) {
            $status = $zone['status'] ?? null;
            $risk = $zone['risk_level'] ?? null;
            $zone['recommended_action'] = getRecommendedAction($status, $risk);
        }
        unset($zone);

        // regenerate report_summary.recommended_actions from top risk zones
        $actions = [];
        // prefer zones sorted by risk_score desc
        $zonesSorted = $zoneRiskSummary['zones'];
        usort($zonesSorted, function ($a, $b) {
            return floatval($b['risk_score'] ?? 0) <=> floatval($a['risk_score'] ?? 0);
        });
        foreach ($zonesSorted as $z) {
            $act = trim((string) ($z['recommended_action'] ?? ''));
            if ($act === '') continue;
            if (! in_array($act, $actions, true)) {
                $actions[] = $act;
            }
            if (count($actions) >= 5) break;
        }

        if (! isset($zoneRiskSummary['report_summary']) || ! is_array($zoneRiskSummary['report_summary'])) {
            $zoneRiskSummary['report_summary'] = [];
        }
        $zoneRiskSummary['report_summary']['recommended_actions'] = $actions;
    }
}

return true;
