<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\TelloClient;

class Tello extends BaseController
{
    private TelloClient $tello;

    public function __construct()
    {
        $this->tello = new TelloClient();
    }

    private function droneId(): string
    {
        $body = $this->request->getJSON(true) ?? [];
        return trim($body['drone_id'] ?? $this->request->getGet('drone_id') ?? '');
    }

    /** GET /api/tello/ping */
    public function ping(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setJSON($this->tello->ping($this->droneId()));
    }

    /** GET /api/tello/state */
    public function state(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setJSON($this->tello->state($this->droneId()));
    }

    /** GET /api/tello/battery */
    public function battery(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setJSON($this->tello->battery($this->droneId()));
    }

    /** GET /api/tello/diagnostics */
    public function diagnostics(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setJSON($this->tello->diagnostics($this->droneId()));
    }

    /** POST /api/tello/stream/on */
    public function streamOn(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setJSON($this->tello->streamOn($this->droneId()));
    }

    /** POST /api/tello/stream/off */
    public function streamOff(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setJSON($this->tello->streamOff($this->droneId()));
    }

    /** POST /api/tello/takeoff */
    public function takeoff(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body   = $this->request->getJSON(true) ?? [];
        $params = [];
        if (isset($body['pause_stream_first'])) {
            $params['pause_stream_first'] = (bool) $body['pause_stream_first'];
        }
        return $this->response->setJSON($this->tello->takeoff($this->droneId(), $params));
    }

    /** POST /api/tello/land */
    public function land(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setJSON($this->tello->land($this->droneId()));
    }

    /** POST /api/tello/emergency */
    public function emergency(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setJSON($this->tello->emergency($this->droneId()));
    }

    /** POST /api/tello/rc */
    public function rc(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];
        $lr   = (int) ($body['lr']  ?? 0);
        $fb   = (int) ($body['fb']  ?? 0);
        $ud   = (int) ($body['ud']  ?? 0);
        $yaw  = (int) ($body['yaw'] ?? 0);

        foreach ([$lr, $fb, $ud, $yaw] as $v) {
            if ($v < -100 || $v > 100) {
                return $this->response->setStatusCode(400)
                    ->setJSON(['error' => 'rc values must be between -100 and 100']);
            }
        }

        return $this->response->setJSON($this->tello->rc($this->droneId(), $lr, $fb, $ud, $yaw));
    }


}
