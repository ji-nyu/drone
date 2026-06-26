<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\FlightLogModel;

class Logs extends BaseController
{
    private FlightLogModel $logModel;

    public function __construct()
    {
        $this->logModel = new FlightLogModel();
    }

    /** GET /api/logs?drone_id=&level=&limit= */
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $droneId = $this->request->getGet('drone_id') ?? '';
        $level   = $this->request->getGet('level') ?? '';
        $limit   = (int)($this->request->getGet('limit') ?? 100);
        $limit   = min(max($limit, 1), 1000);
        $offset  = (int)($this->request->getGet('offset') ?? 0);

        $logs = $this->logModel->getRecent($droneId, $level, $limit, $offset);
        return $this->response->setJSON(['status' => 'ok', 'data' => $logs]);
    }

    /** POST /api/logs/clear */
    public function clear(): \CodeIgniter\HTTP\ResponseInterface
    {
        $droneId = $this->request->getGet('drone_id') ?? '';
        if ($droneId) {
            $this->logModel->where('drone_id', $droneId)->delete();
        } else {
            $this->logModel->truncate();
        }
        return $this->response->setJSON(['status' => 'ok']);
    }

    /** POST /api/logs */
    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body    = $this->request->getJSON(true) ?? [];
        $droneId = trim($body['drone_id'] ?? '');
        $level   = $body['level'] ?? 'info';
        $message = trim($body['message'] ?? '');

        if (!$droneId || !$message) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'drone_id and message required']);
        }

        $validLevels = ['info', 'warn', 'error', 'ok'];
        if (!in_array($level, $validLevels, true)) {
            $level = 'info';
        }

        $this->logModel->addLog(
            $droneId,
            $level,
            substr($message, 0, 500),
            isset($body['mission_id']) ? (int)$body['mission_id'] : null
        );

        return $this->response->setStatusCode(201)
            ->setJSON(['status' => 'ok']);
    }
}
