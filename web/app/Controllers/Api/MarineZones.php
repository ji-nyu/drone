<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\MarineZoneModel;

class MarineZones extends BaseController
{
    private MarineZoneModel $zoneModel;

    public function __construct()
    {
        $this->zoneModel = new MarineZoneModel();
    }

    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $this->zoneModel->ensureTable();
        $zones = $this->zoneModel->where('is_active', 1)->findAll();
        return $this->response->setJSON(['status' => 'ok', 'data' => $zones]);
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $this->zoneModel->ensureTable();
        $body = $this->request->getJSON(true) ?? [];
        $zones = $body['zones'] ?? [];

        if (empty($zones)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'no zones provided']);
        }

        $this->zoneModel->where('is_active', 1)->delete();

        $saved = [];
        foreach ($zones as $zone) {
            $payload = [
                'zone_id' => trim($zone['zone_id'] ?? ''),
                'zone_name' => trim($zone['zone_name'] ?? '사용자 구역'),
                'beach_name' => trim($zone['beach_name'] ?? '함덕 해수욕장'),
                'center_lat' => $zone['center']['latitude'] ?? null,
                'center_lng' => $zone['center']['longitude'] ?? null,
                'polygon_geojson' => json_encode($zone['geojson'] ?? [], JSON_UNESCAPED_UNICODE),
                'risk' => (int) ($zone['risk'] ?? 0),
                'count' => (int) ($zone['count'] ?? 0),
                'level' => trim($zone['level'] ?? 'normal'),
                'is_active' => 1,
            ];

            if (empty($payload['zone_id'])) {
                continue;
            }

            $this->zoneModel->protect(false)->insert($payload, false);
            $saved[] = $payload;
        }

        return $this->response->setStatusCode(201)
            ->setJSON(['status' => 'ok', 'saved' => count($saved), 'data' => $saved]);
    }
}
