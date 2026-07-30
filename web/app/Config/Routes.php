<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ── 웹 페이지 ────────────────────────────────────
$routes->get('/',               'Drone::index');
$routes->get('drone',           'Drone::index');
$routes->get('drone/marine-trash', 'Drone::marineTrash');
$routes->get('drone/marine-trash-data', 'Drone::marineTrashData');
$routes->get('api/detections/log', 'Drone::detectionLog');
$routes->get('api/marine/collection-points', 'Drone::collectionPoints');
$routes->get('drone/control',   'Drone::control');
$routes->get('drone/missions',  'Drone::missions');
$routes->get('drone/logs',      'Drone::logs');
$routes->get('drone/settings',  'Drone::settings');

// ── 스트림 테스트 ─────────────────────────────────
$routes->get('test',            'Test::index');
$routes->get('test/snapshot',   'Test::snapshot');
$routes->get('test/stream',     'Test::stream');

// ── 스트림 프록시 ─────────────────────────────────
$routes->get('stream/mjpeg',    'Test::stream');
$routes->get('stream/snapshot', 'Test::snapshot');

// ── REST API ─────────────────────────────────────
$routes->group('api', ['namespace' => 'App\Controllers\Api'], function ($routes) {

    // 드론 등록/조회/삭제
    $routes->get('drones',                          'Drones::index');
    $routes->post('drones',                         'Drones::create');
    $routes->post('drones/(:segment)/update',       'Drones::update/$1');
    $routes->post('drones/(:segment)/delete',       'Drones::delete/$1');
    $routes->get('drones/(:segment)/status',        'Drones::status/$1');
    $routes->post('drones/(:segment)/telemetry',    'Drones::telemetry/$1');

    // 미션
    $routes->get('missions',                                    'Missions::index');
    $routes->post('missions',                                   'Missions::create');
    $routes->get('missions/(:num)',                             'Missions::show/$1');
    $routes->post('missions/(:num)/state',                      'Missions::updateState/$1');
    $routes->post('missions/(:num)/retry',                      'Missions::retry/$1');
    $routes->post('missions/(:num)/actions/(:num)',              'Missions::updateAction/$1/$2');

    // 미션 배송확인
    $routes->post('missions/(:num)/confirm',                    'Missions::confirm/$1');
    $routes->post('missions/(:num)/start',                      'Missions::start/$1');
    $routes->post('missions/(:num)/run',                        'Missions::run/$1');
    $routes->post('missions/(:num)/update',                     'Missions::updateDraft/$1');
    $routes->post('missions/(:num)/delete',                     'Missions::delete/$1');

    // 비행 로그
    $routes->get('logs',          'Logs::index');
    $routes->post('logs',         'Logs::create');
    $routes->post('logs/clear',   'Logs::clear');

    // 해양 구역
    $routes->post('marine-zones', 'MarineZones::create');
    $routes->get('marine-zones',  'MarineZones::index');

    // 조사 Word 보고서 (test3.py)
    $routes->post('reports/generate',           'Reports::generate');
    $routes->get('reports/download/(:segment)', 'Reports::download/$1');

    // 환경설정
    $routes->get('settings/tello',  'EnvSettings::getTello');
    $routes->post('settings/tello', 'EnvSettings::saveTello');

    // Tello 파이썬 API 브릿지
    $routes->get('tello/ping',          'Tello::ping');
    $routes->get('tello/state',         'Tello::state');
    $routes->get('tello/battery',       'Tello::battery');
    $routes->get('tello/diagnostics',   'Tello::diagnostics');
    $routes->post('tello/stream/on',    'Tello::streamOn');
    $routes->post('tello/stream/off',   'Tello::streamOff');
    $routes->post('tello/takeoff',      'Tello::takeoff');
    $routes->post('tello/land',         'Tello::land');
    $routes->post('tello/emergency',    'Tello::emergency');
    $routes->post('tello/rc',           'Tello::rc');
});
