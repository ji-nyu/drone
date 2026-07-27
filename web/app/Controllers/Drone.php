<?php

namespace App\Controllers;

use App\Models\DroneModel;

class Drone extends BaseController
{
    private function render(string $page, string $title, string $icon, string $view, array $data = []): string
    {
        $drones = (new DroneModel())->where('is_active', 1)->findAll();

        $layout = [
            'activePage'   => $page,
            'pageTitle'    => $title,
            'pageIcon'     => $icon,
            'contentClass' => in_array($page, ['control', 'missions']) ? 'flex-content' : '',
            'droneList'    => $drones,
            'cfgApiBase'    => env('tello.api_url',    'http://localhost:8000'),
            'cfgApiToken'   => env('tello.api_token',  'tello-api-secret-change-me'),
            'cfgStreamPath' => env('stream.path',      '/stream'),
            'cfgPollMs'     => (int) env('app.poll_ms',     1000),
            'cfgDroneCount' => (int) env('app.drone_count', 10),
            'cfgDronePrefix'=> env('app.drone_prefix', 'TT'),
        ];

        return view('drone/layout/header', $layout)
             . view('drone/pages/' . $view, $data)
             . view('drone/layout/footer');
    }

    private function loadJsonFile(string $path, array $default): array
    {
        if (!is_file($path)) {
            return $default;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return $default;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : $default;
    }

    public function index(): string
    {
        $homeIcon = '<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3.5 9.5L10 3.5l6.5 6V16a1 1 0 01-1 1h-3.5v-4.5H8V17H4.5a1 1 0 01-1-1V9.5z"/></svg>';
        return $this->render('dashboard', '대시보드', $homeIcon, 'dashboard');
    }

    public function marineTrash(): string
    {
        $marineZonesDefault = [
            'map_center' => [
                'latitude' => 33.5455,
                'longitude' => 126.6698,
                'zoom' => 14,
            ],
            'zones' => [],
        ];

        $inspectionDetectionsDefault = [
            'mission' => [],
            'detections' => [],
        ];

        $zoneRiskSummaryDefault = [
            'zones' => [],
            'top3' => [],
            'report_summary' => [],
        ];

        $marineZones = $this->loadJsonFile(APPPATH . 'Data/marine_zones_fixed.json', $marineZonesDefault);
        $inspectionDetections = $this->loadJsonFile(APPPATH . 'Data/inspection_detections_raw.json', $inspectionDetectionsDefault);
        $zoneRiskSummary = $this->loadJsonFile(APPPATH . 'Data/zone_risk_summary.json', $zoneRiskSummaryDefault);

        // Ensure recommended_action values are computed consistently from status/risk_level.
        // If a generator script exists, it should produce these, but compute as fallback here.
        try {
            if (is_file(APPPATH . 'Helpers/recommendation_helper.php')) {
                require_once APPPATH . 'Helpers/recommendation_helper.php';
                if (function_exists('recomputeZoneRecommendedActions')) {
                    recomputeZoneRecommendedActions($zoneRiskSummary);
                }
            }
        } catch (\Throwable $e) {
            // non-fatal, prefer to continue with existing JSON
        }

        return $this->render(
            'marine_trash',
            '해양쓰레기',
            '<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 7.5h10v8.5a1.5 1.5 0 01-1.5 1.5h-7A1.5 1.5 0 015 16V7.5z"/><path d="M3.5 7.5h13M8 7.5V5.5a2 2 0 014 0v2"/></svg>',
            'marine_trash',
            [
                'marineZones' => $marineZones,
                'inspectionDetections' => $inspectionDetections,
                'zoneRiskSummary' => $zoneRiskSummary,
            ]
        );
    }

    public function marineTrashData()
    {
        $marineZonesDefault = [
            'map_center' => [
                'latitude' => 33.5455,
                'longitude' => 126.6698,
                'zoom' => 14,
            ],
            'zones' => [],
        ];

        $inspectionDetectionsDefault = [
            'mission' => [],
            'detections' => [],
        ];

        $zoneRiskSummaryDefault = [
            'zones' => [],
            'top3' => [],
            'report_summary' => [],
        ];

        $marineZones = $this->loadJsonFile(APPPATH . 'Data/marine_zones_fixed.json', $marineZonesDefault);
        $inspectionDetections = $this->loadJsonFile(APPPATH . 'Data/inspection_detections_raw.json', $inspectionDetectionsDefault);
        $zoneRiskSummary = $this->loadJsonFile(APPPATH . 'Data/zone_risk_summary.json', $zoneRiskSummaryDefault);

        return $this->response->setJSON([
            'fixed' => $marineZones,
            'detection' => $inspectionDetections,
            'risk' => $zoneRiskSummary,
        ]);
    }

    public function collectionPoints()
    {
        $path = APPPATH . 'Data/collection_points.json';

        if (!is_file($path)) {
            return $this->response->setStatusCode(404)->setJSON([
                'ok' => false,
                'message' => 'collection_points.json을 찾을 수 없습니다.',
            ]);
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return $this->response->setStatusCode(500)->setJSON([
                'ok' => false,
                'message' => 'collection_points.json을 읽을 수 없습니다.',
            ]);
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $this->response->setStatusCode(500)->setJSON([
                'ok' => false,
                'message' => 'collection_points.json 형식이 올바르지 않습니다.',
            ]);
        }

        return $this->response->setJSON([
            'ok' => true,
            'data' => $data,
        ]);
    }

    public function control(): string
    {
        $droneId = $this->request->getGet('drone') ?? 'TT-01';
        $icon = '<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 3.5l1.2 3.2h3.4l-2.7 2 1 3.3L10 10.5 7.1 12l1-3.3-2.7-2h3.4L10 3.5z"/><circle cx="10" cy="15.5" r="1.2" fill="currentColor" stroke="none"/></svg>';
        return $this->render('control', '드론 관제', $icon, 'control', [
            'selectedDrone' => esc($droneId),
        ]);
    }

    public function missions(): string
    {
        $icon = '<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="2.5" width="12" height="15" rx="1.5"/><path d="M7 7h6M7 10.5h6M7 14h3.5"/></svg>';
        return $this->render('missions', '임무 현황', $icon, 'missions');
    }

    public function logs(): string
    {
        $icon = '<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="7"/><circle cx="10" cy="10" r="3.5"/><circle cx="10" cy="10" r="1" fill="currentColor" stroke="none"/></svg>';
        return $this->render('logs', '비행 로그', $icon, 'logs');
    }

    public function settings(): string
    {
        $icon = '<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="2.5"/><path d="M10 2.5v2M10 15.5v2M2.5 10h2M15.5 10h2M4.7 4.7l1.4 1.4M13.9 13.9l1.4 1.4M4.7 15.3l1.4-1.4M13.9 6.1l1.4-1.4"/></svg>';
        return $this->render('settings', '설정', $icon, 'settings');
    }
}
