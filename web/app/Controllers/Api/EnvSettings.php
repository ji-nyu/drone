<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class EnvSettings extends BaseController
{
    private string $envPath;

    public function __construct()
    {
        $this->envPath = ROOTPATH . '.env';
    }

    /** GET /api/settings/tello */
    public function getTello(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => [
                'api_url'     => env('tello.api_url',     'http://localhost:8000'),
                'api_token'   => env('tello.api_token',   'tello-api-secret-change-me'),
                'api_timeout' => (int) env('tello.api_timeout', 10),
            ],
        ]);
    }

    /** POST /api/settings/tello */
    public function saveTello(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];

        $allowed = ['api_url', 'api_token', 'api_timeout'];
        $updates = [];

        if (isset($body['api_url']))     $updates['tello.api_url']     = trim($body['api_url']);
        if (isset($body['api_token']))   $updates['tello.api_token']   = trim($body['api_token']);
        if (isset($body['api_timeout'])) $updates['tello.api_timeout'] = (int) $body['api_timeout'];

        if (empty($updates)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'nothing to update']);
        }

        $this->writeEnv($updates);

        return $this->response->setJSON(['status' => 'ok']);
    }

    private function writeEnv(array $updates): void
    {
        $lines = file_exists($this->envPath)
            ? file($this->envPath, FILE_IGNORE_NEW_LINES)
            : [];

        foreach ($updates as $key => $value) {
            $found = false;
            foreach ($lines as $i => $line) {
                if (preg_match('/^\s*#?\s*' . preg_quote($key, '/') . '\s*=/', $line)) {
                    $lines[$i] = "{$key} = {$value}";
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $lines[] = "{$key} = {$value}";
            }
        }

        file_put_contents($this->envPath, implode("\n", $lines) . "\n");
    }
}
