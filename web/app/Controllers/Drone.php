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

    public function index(): string
    {
        return $this->render('dashboard', '대시보드', '▦', 'dashboard');
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
