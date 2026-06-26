<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\DroneModel;
use App\Models\DroneTelemetryModel;
use App\Models\MissionModel;

class Drones extends BaseController
{
    private DroneModel $droneModel;
    private DroneTelemetryModel $telemetryModel;
    private MissionModel $missionModel;

    public function __construct()
    {
        $this->droneModel     = new DroneModel();
        $this->telemetryModel = new DroneTelemetryModel();
        $this->missionModel   = new MissionModel();
    }

    /** GET /api/drones */
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $drones = $this->droneModel->where('is_active', 1)->findAll();
        foreach ($drones as &$drone) {
            $latest                = $this->telemetryModel->getLatest($drone['drone_id']);
            $mission               = $this->missionModel->getActiveMission($drone['drone_id']);
            $drone['online']       = $this->telemetryModel->isOnline($drone['drone_id']);
            $drone['battery']      = $latest['battery']  ?? null;
            $drone['altitude']     = $latest['altitude'] ?? null;
            $drone['mission_state']= $mission['state']   ?? null;
        }
        unset($drone);
        return $this->response->setJSON(['status' => 'ok', 'data' => $drones]);
    }

    /** POST /api/drones */
    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body     = $this->request->getJSON(true) ?? [];
        $droneId  = trim($body['drone_id'] ?? '');
        $ip       = trim($body['ip'] ?? '');
        $label    = trim($body['label'] ?? $droneId);

        if (!$droneId || !$ip) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'drone_id and ip are required']);
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'invalid IPv4 address']);
        }

        if ($this->droneModel->findByDroneId($droneId)) {
            return $this->response->setStatusCode(409)
                ->setJSON(['status' => 'error', 'message' => 'drone_id already exists']);
        }

        $id = $this->droneModel->insert([
            'drone_id' => $droneId,
            'ip'       => $ip,
            'label'    => $label,
            'model'    => $body['model'] ?? 'Tello TT',
        ], true);

        return $this->response->setStatusCode(201)
            ->setJSON(['status' => 'ok', 'id' => $id]);
    }

    /** PUT /api/drones/{drone_id} */
    public function update(string $droneId = ''): \CodeIgniter\HTTP\ResponseInterface
    {
        $row = $this->droneModel->findByDroneId($droneId);
        if (!$row) {
            return $this->response->setStatusCode(404)
                ->setJSON(['status' => 'error', 'message' => 'not found']);
        }

        $body  = $this->request->getJSON(true) ?? [];
        $data  = [];

        if (isset($body['ip'])) {
            $ip = trim($body['ip']);
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $this->response->setStatusCode(400)
                    ->setJSON(['status' => 'error', 'message' => 'invalid IPv4 address']);
            }
            $data['ip'] = $ip;
        }
        if (isset($body['label'])) {
            $data['label'] = trim($body['label']);
        }

        if (empty($data)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'nothing to update']);
        }

        $this->droneModel->update($row['id'], $data);
        return $this->response->setJSON(['status' => 'ok']);
    }

    /** DELETE /api/drones/{drone_id} */
    public function delete(string $droneId = ''): \CodeIgniter\HTTP\ResponseInterface
    {
        $row = $this->droneModel->findByDroneId($droneId);
        if (!$row) {
            return $this->response->setStatusCode(404)
                ->setJSON(['status' => 'error', 'message' => 'not found']);
        }

        $this->droneModel->update($row['id'], ['is_active' => 0]);
        return $this->response->setJSON(['status' => 'ok']);
    }

    /** GET /api/drones/{drone_id}/status */
    public function status(string $droneId = ''): \CodeIgniter\HTTP\ResponseInterface
    {
        $latest  = $this->telemetryModel->getLatest($droneId);
        $online  = $this->telemetryModel->isOnline($droneId);
        $mission = $this->missionModel->getActiveMission($droneId);

        $telemetry = $latest ?? [];
        unset($telemetry['mission_state']);
        $telemetry['mission_state'] = $mission['state']   ?? null;
        $telemetry['mission_id']    = $mission['id']      ?? null;

        return $this->response->setJSON([
            'status'    => 'ok',
            'drone_id'  => $droneId,
            'online'    => $online,
            'telemetry' => $telemetry,
        ]);
    }

    /** POST /api/drones/{drone_id}/telemetry */
    public function telemetry(string $droneId = ''): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];

        $allowed = ['battery', 'altitude', 'speed', 'temperature', 'yaw'];
        $data    = array_intersect_key($body, array_flip($allowed));

        if (empty($data)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'no telemetry fields']);
        }

        $this->telemetryModel->push($droneId, $data);
        $this->droneModel->touchLastSeen($droneId);

        return $this->response->setJSON(['status' => 'ok']);
    }
}
