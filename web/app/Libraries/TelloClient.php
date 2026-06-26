<?php

namespace App\Libraries;

class TelloClient
{
    private string $baseUrl;
    private string $authToken;
    private int    $timeout;
    private int    $rpcId = 1;

    public function __construct()
    {
        $this->baseUrl   = rtrim(env('tello.api_url',   'http://localhost:8000'), '/');
        $this->authToken = env('tello.api_token', 'tello-api-secret-change-me');
        $this->timeout   = (int) env('tello.api_timeout', 0);
    }

    /**
     * JSON-RPC 2.0 호출
     */
    public function rpc(string $method, array $params = []): array
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
            'id'      => $this->rpcId++,
        ]);

        $ch = curl_init($this->baseUrl . '/rpc');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: ' . $this->authToken,
            ],
        ]);

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno) {
            return ['error' => ['code' => -1, 'message' => 'curl error: ' . curl_strerror($errno)]];
        }

        return json_decode($body, true) ?? ['error' => ['code' => -1, 'message' => 'invalid json']];
    }

    // ── 개별 메서드 래퍼 ─────────────────────────────

    public function ping(string $droneId = ''): array
    {
        return $this->rpc('ping', $this->p([], $droneId));
    }

    public function streamOn(string $droneId = ''): array
    {
        return $this->rpc('stream_on', $this->p([], $droneId));
    }

    public function streamOff(string $droneId = ''): array
    {
        return $this->rpc('stream_off', $this->p([], $droneId));
    }

    public function takeoff(string $droneId = '', array $params = []): array
    {
        return $this->rpc('takeoff', $this->p($params, $droneId));
    }

    public function land(string $droneId = ''): array
    {
        return $this->rpc('land', $this->p([], $droneId));
    }

    public function emergency(string $droneId = ''): array
    {
        return $this->rpc('emergency', $this->p([], $droneId));
    }

    public function rc(string $droneId, int $lr, int $fb, int $ud, int $yaw): array
    {
        return $this->rpc('rc', $this->p(['lr' => $lr, 'fb' => $fb, 'ud' => $ud, 'yaw' => $yaw], $droneId));
    }

    public function state(string $droneId = ''): array
    {
        return $this->rpc('state', $this->p([], $droneId));
    }

    public function battery(string $droneId = ''): array
    {
        return $this->rpc('battery', $this->p([], $droneId));
    }

    public function diagnostics(string $droneId = ''): array
    {
        return $this->rpc('diagnostics', $this->p([], $droneId));
    }


    public function startMission(int $missionId, string $droneId): array
    {
        $payload = json_encode([
            'mission_id' => $missionId,
            'drone_id'   => $droneId,
        ]);

        $ch = curl_init($this->baseUrl . '/missions/start');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: ' . $this->authToken,
            ],
        ]);

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno) {
            return ['error' => ['code' => -1, 'message' => 'curl error: ' . curl_strerror($errno)]];
        }

        return json_decode($body, true) ?? ['error' => ['code' => -1, 'message' => 'invalid json']];
    }

    /** drone_id 를 params 에 병합 */
    private function p(array $params, string $droneId): array
    {
        if ($droneId !== '') {
            $params['drone_id'] = $droneId;
        }
        return $params;
    }
}
