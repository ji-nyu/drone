<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class Test extends BaseController
{
    private string $apiUrl;
    private string $token;

    public function __construct()
    {
        $this->apiUrl = rtrim(env('tello.api_url',   'http://localhost:8000'), '/');
        $this->token  = env('tello.api_token', 'tello-api-secret-change-me');
    }

    /** GET /test */
    public function index(): string
    {
        return view('test/index');
    }

    /** GET /test/snapshot?drone_id=TT-01
     *  백엔드 /snapshot.jpg 를 프록시해 브라우저에 JPEG 반환
     */
    public function snapshot(): void
    {
        $droneId = trim($this->request->getGet('drone_id') ?? '');
        $url     = $this->apiUrl . '/snapshot.jpg' . ($droneId !== '' ? '?drone_id=' . urlencode($droneId) : '');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $this->token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || empty($body)) {
            http_response_code(503);
            echo 'no frame';
            return;
        }

        header('Content-Type: image/jpeg');
        header('Cache-Control: no-store');
        echo $body;
    }

    /** GET /test/stream?drone_id=TT-01
     *  백엔드 /stream.mjpeg 를 실시간 프록시
     */
    public function stream(): void
    {
        $droneId = trim($this->request->getGet('drone_id') ?? '');
        $url     = $this->apiUrl . '/stream.mjpeg' . ($droneId !== '' ? '?drone_id=' . urlencode($droneId) : '');

        set_time_limit(0);
        ignore_user_abort(true);
        ini_set('output_buffering', 'off');
        ini_set('implicit_flush', '1');

        while (ob_get_level()) {
            ob_end_clean();
        }

        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
            apache_setenv('dont-vary', '1');
        }

        // Content-Type은 백엔드 응답에서 그대로 가져옴 (boundary 불일치 방지)
        header('Cache-Control: no-cache, no-store');
        header('Content-Encoding: identity');
        header('Connection: keep-alive');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $this->token],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_HEADERFUNCTION => function ($ch, $header) {
                $lower = strtolower(trim($header));
                if (str_starts_with($lower, 'content-type:')) {
                    header(trim($header), true);
                }
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION  => function ($ch, $data) {
                echo $data;
                ob_flush();
                flush();
                return connection_aborted() ? -1 : strlen($data);
            },
        ]);

        curl_exec($ch);
        curl_close($ch);
    }
}
