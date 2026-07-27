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
        return $this->render('dashboard', '대시보드', '▦', 'dashboard');
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
            '🌊',
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
        return $this->render('control', '드론 관제', '✦', 'control', [
            'selectedDrone' => esc($droneId),
        ]);
    }

    public function missions(): string
    {
        return $this->render('missions', '임무 현황', '≡', 'missions');
    }

    public function logs(): string
    {
        return $this->render('logs', '비행 로그', '◈', 'logs');
    }

    public function settings(): string
    {
        return $this->render('settings', '설정', '⊙', 'settings');
    }
}
