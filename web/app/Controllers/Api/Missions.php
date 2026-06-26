<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\TelloClient;
use App\Models\MissionModel;
use App\Models\MissionActionModel;
use App\Models\DroneTelemetryModel;
use App\Models\FlightLogModel;

class Missions extends BaseController
{
    private MissionModel $missionModel;
    private MissionActionModel $actionModel;
    private DroneTelemetryModel $telemetryModel;
    private FlightLogModel $logModel;

    public function __construct()
    {
        $this->missionModel   = new MissionModel();
        $this->actionModel    = new MissionActionModel();
        $this->telemetryModel = new DroneTelemetryModel();
        $this->logModel       = new FlightLogModel();
    }

    /** GET /api/missions */
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $droneId = $this->request->getGet('drone_id');
        $q = $this->missionModel->orderBy('id', 'DESC')->limit(100);
        if ($droneId) {
            $q->where('drone_id', $droneId);
        }
        $missions = $q->findAll();

        if ($missions) {
            $ids    = array_column($missions, 'id');
            $counts = [];
            foreach ($this->actionModel->select('mission_id, COUNT(*) as cnt')
                         ->whereIn('mission_id', $ids)
                         ->groupBy('mission_id')
                         ->findAll() as $r) {
                $counts[(int)$r['mission_id']] = (int)$r['cnt'];
            }
            foreach ($missions as &$m) {
                $m['action_count'] = $counts[$m['id']] ?? 0;
            }
            unset($m);
        }

        return $this->response->setJSON(['status' => 'ok', 'data' => $missions]);
    }

    /** POST /api/missions */
    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body    = $this->request->getJSON(true) ?? [];
        $droneId = trim($body['drone_id'] ?? '');
        $actions = $body['actions'] ?? [];

        if (!$droneId) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'drone_id required']);
        }

        if (empty($actions) || !is_array($actions)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'actions array required']);
        }

        $validationError = $this->validateActions($actions);
        if ($validationError) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => $validationError]);
        }

        $missionId = $this->missionModel->insert([
            'drone_id'   => $droneId,
            'state'      => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        // 액션 일괄 삽입
        $rows = [];
        foreach ($actions as $i => $a) {
            $rows[] = [
                'mission_id' => $missionId,
                'seq'        => $i + 1,
                'type'       => $a['type'],
                'value'      => isset($a['value']) ? (int)$a['value'] : null,
                'result'     => 'pending',
            ];
        }
        if ($rows) {
            $this->actionModel->insertBatch($rows);
        }

        $this->logModel->addLog($droneId, 'info', "미션 #{$missionId} 생성됨", $missionId);

        return $this->response->setStatusCode(201)
            ->setJSON(['status' => 'ok', 'mission_id' => $missionId]);
    }

    /** GET /api/missions/{id} */
    public function show(int $id = 0): \CodeIgniter\HTTP\ResponseInterface
    {
        $mission = $this->missionModel->find($id);
        if (!$mission) {
            return $this->response->setStatusCode(404)
                ->setJSON(['status' => 'error', 'message' => 'not found']);
        }

        $mission['actions'] = $this->actionModel->getByMission($id);
        return $this->response->setJSON(['status' => 'ok', 'data' => $mission]);
    }

    /** POST /api/missions/{id}/delete */
    public function delete(int $id = 0): \CodeIgniter\HTTP\ResponseInterface
    {
        $mission = $this->missionModel->find($id);
        if (!$mission) {
            return $this->response->setStatusCode(404)
                ->setJSON(['status' => 'error', 'message' => 'not found']);
        }

        if (in_array($mission['state'], ['preparing', 'flying', 'arrived', 'returning'], true)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => '비행 중인 미션은 삭제할 수 없습니다']);
        }

        $this->actionModel->where('mission_id', $id)->delete();
        $this->missionModel->delete($id);
        $this->logModel->addLog($mission['drone_id'], 'info', "미션 #{$id} 삭제됨");

        return $this->response->setJSON(['status' => 'ok']);
    }

    /** POST /api/missions/{id}/start — draft → requested */
    public function start(int $id = 0): \CodeIgniter\HTTP\ResponseInterface
    {
        $mission = $this->missionModel->find($id);
        if (!$mission) {
            return $this->response->setStatusCode(404)
                ->setJSON(['status' => 'error', 'message' => 'not found']);
        }
        if ($mission['state'] !== 'draft') {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'mission is not in draft state']);
        }

        $droneId = $mission['drone_id'];

        if ($this->missionModel->getActiveMission($droneId)) {
            return $this->response->setStatusCode(409)
                ->setJSON(['status' => 'error', 'message' => 'drone already has an active mission']);
        }

        $tele = $this->telemetryModel->getLatest($droneId);
        if ($tele && isset($tele['battery']) && $tele['battery'] < 30) {
            return $this->response->setStatusCode(422)
                ->setJSON(['status' => 'error', 'message' => 'battery too low (< 30%)']);
        }

        $this->missionModel->setState($id, 'requested', ['started_at' => date('Y-m-d H:i:s')]);
        $this->logModel->addLog($droneId, 'info', "미션 #{$id} 시작됨", $id);

        return $this->response->setJSON(['status' => 'ok']);
    }

    /** PUT /api/missions/{id} — draft 상태 액션 수정 */
    public function updateDraft(int $id = 0): \CodeIgniter\HTTP\ResponseInterface
    {
        $mission = $this->missionModel->find($id);
        if (!$mission) {
            return $this->response->setStatusCode(404)
                ->setJSON(['status' => 'error', 'message' => 'not found']);
        }
        if ($mission['state'] !== 'draft') {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'only draft missions can be edited']);
        }

        $body    = $this->request->getJSON(true) ?? [];
        $actions = $body['actions'] ?? [];

        if (empty($actions) || !is_array($actions)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'actions array required']);
        }

        $validationError = $this->validateActions($actions);
        if ($validationError) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => $validationError]);
        }

        $this->actionModel->where('mission_id', $id)->delete();

        $rows = [];
        foreach ($actions as $i => $a) {
            $rows[] = [
                'mission_id' => $id,
                'seq'        => $i + 1,
                'type'       => $a['type'],
                'value'      => isset($a['value']) ? (int)$a['value'] : null,
                'result'     => 'pending',
            ];
        }
        if ($rows) {
            $this->actionModel->insertBatch($rows);
        }

        return $this->response->setJSON(['status' => 'ok']);
    }

    /** PATCH /api/missions/{id}/state  — Python이 상태 갱신 */
    public function updateState(int $id = 0): \CodeIgniter\HTTP\ResponseInterface
    {
        $mission = $this->missionModel->find($id);
        if (!$mission) {
            return $this->response->setStatusCode(404)
                ->setJSON(['status' => 'error', 'message' => 'not found']);
        }

        $body  = $this->request->getJSON(true) ?? [];
        $state = $body['state'] ?? '';

        if (!in_array($state, MissionModel::STATES, true)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'invalid state']);
        }

        $extra = [];
        if (in_array($state, ['delivered', 'failed'], true)) {
            $extra['ended_at'] = date('Y-m-d H:i:s');
        }
        if ($state === 'failed' && isset($body['fail_reason'])) {
            $extra['fail_reason'] = substr($body['fail_reason'], 0, 200);
        }
        if ($state === 'delivered') {
            $extra['delivery_confirmed']    = 1;
            $extra['delivery_confirmed_at'] = date('Y-m-d H:i:s');
        }

        $this->missionModel->setState($id, $state, $extra);
        $this->logModel->addLog($mission['drone_id'], 'info', "미션 #{$id} 상태 → {$state}", $id);

        return $this->response->setJSON(['status' => 'ok']);
    }

    /** POST /api/missions/{id}/retry */
    public function retry(int $id = 0): \CodeIgniter\HTTP\ResponseInterface
    {
        $original = $this->missionModel->find($id);
        if (!$original || $original['state'] !== 'failed') {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'mission not found or not in failed state']);
        }

        $droneId = $original['drone_id'];

        // 원본 액션 복사
        $originalActions = $this->actionModel->getByMission($id);

        $newId = $this->missionModel->insert([
            'drone_id'   => $droneId,
            'state'      => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        $rows = [];
        foreach ($originalActions as $a) {
            $rows[] = [
                'mission_id' => $newId,
                'seq'        => $a['seq'],
                'type'       => $a['type'],
                'value'      => $a['value'],
                'result'     => 'pending',
            ];
        }
        if ($rows) {
            $this->actionModel->insertBatch($rows);
        }

        $this->logModel->addLog($droneId, 'info', "미션 #{$id} 재시도 → 신규 미션 #{$newId}", $newId);

        return $this->response->setStatusCode(201)
            ->setJSON(['status' => 'ok', 'mission_id' => $newId]);
    }

    /** POST /api/missions/{id}/run — 액션을 PHP에서 순차 실행 */
    public function run(int $id = 0): \CodeIgniter\HTTP\ResponseInterface
    {
        $mission = $this->missionModel->find($id);
        if (!$mission) {
            return $this->response->setStatusCode(404)
                ->setJSON(['status' => 'error', 'message' => 'not found']);
        }
        if ($mission['state'] !== 'draft') {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => '초안(draft) 상태의 미션만 실행할 수 있습니다']);
        }

        $droneId = $mission['drone_id'];
        $actions = $this->actionModel->getByMission($id);

        if (empty($actions)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => '실행할 액션이 없습니다']);
        }

        if ($this->missionModel->getActiveMission($droneId)) {
            return $this->response->setStatusCode(409)
                ->setJSON(['status' => 'error', 'message' => '해당 드론에 이미 활성 미션이 있습니다']);
        }

        $this->missionModel->setState($id, 'flying', ['started_at' => date('Y-m-d H:i:s')]);
        $this->logModel->addLog($droneId, 'info', "미션 #{$id} 실행 요청", $id);

        $tello = new TelloClient();
        $res   = $tello->startMission($id, $droneId);

        if (isset($res['error'])) {
            $errMsg = $res['error']['message'] ?? '알 수 없는 오류';
            $this->missionModel->setState($id, 'failed', [
                'ended_at'    => date('Y-m-d H:i:s'),
                'fail_reason' => $errMsg,
            ]);
            $this->logModel->addLog($droneId, 'error', "미션 #{$id} 실행 요청 실패: {$errMsg}", $id);
            return $this->response->setStatusCode(502)
                ->setJSON(['status' => 'error', 'message' => $errMsg]);
        }

        $this->logModel->addLog($droneId, 'info', "미션 #{$id} 파이썬 전달 완료", $id);

        return $this->response->setJSON(['status' => 'ok']);
    }

    private function validateActions(array $actions): ?string
    {
        // value 필수 타입
        $requiresValue = ['forward', 'back', 'left', 'right', 'up', 'down', 'rotate_cw', 'rotate_ccw', 'hover'];
        // value 없는 타입
        $noValue       = ['land', 'deliver'];
        $allTypes      = array_merge($requiresValue, $noValue, ['takeoff']);

        foreach ($actions as $i => $a) {
            $seq  = $i + 1;
            $type = $a['type'] ?? '';

            if (!in_array($type, $allTypes, true)) {
                return "seq {$seq}: 지원하지 않는 액션 타입 '{$type}'";
            }

            if (in_array($type, $requiresValue, true)) {
                if (!isset($a['value']) || !is_numeric($a['value']) || (int)$a['value'] <= 0) {
                    return "seq {$seq}: '{$type}'은 양의 정수 value가 필요합니다";
                }
            }
        }

        return null;
    }

    private function dispatchAction(TelloClient $tello, string $droneId, string $type, ?int $value): array
    {
        return match($type) {
            'takeoff'    => $tello->takeoff($droneId),
            'land'       => $tello->land($droneId),
            'forward'    => $this->rcMove($tello, $droneId, lr:0,  fb:50,  ud:0,  yaw:0,  cm:(int)$value),
            'back'       => $this->rcMove($tello, $droneId, lr:0,  fb:-50, ud:0,  yaw:0,  cm:(int)$value),
            'left'       => $this->rcMove($tello, $droneId, lr:-50, fb:0,  ud:0,  yaw:0,  cm:(int)$value),
            'right'      => $this->rcMove($tello, $droneId, lr:50,  fb:0,  ud:0,  yaw:0,  cm:(int)$value),
            'up'         => $this->rcMove($tello, $droneId, lr:0,  fb:0,   ud:50, yaw:0,  cm:(int)$value),
            'down'       => $this->rcMove($tello, $droneId, lr:0,  fb:0,   ud:-50, yaw:0, cm:(int)$value),
            'rotate_cw'  => $this->rcMove($tello, $droneId, lr:0,  fb:0,   ud:0,  yaw:50, cm:(int)$value),
            'rotate_ccw' => $this->rcMove($tello, $droneId, lr:0,  fb:0,   ud:0,  yaw:-50, cm:(int)$value),
            'hover'      => $this->rcMove($tello, $droneId, lr:0,  fb:0,   ud:0,  yaw:0,  cm:(int)($value ?? 3) * 50),
            'deliver'    => $tello->rc($droneId, 0, 0, 0, 0),
            default      => ['error' => ['code' => -1, 'message' => "지원하지 않는 액션: {$type}"]],
        };
    }

    /**
     * rc 명령으로 이동 후 정지
     * speed=50 기준 약 50cm/s → cm/50 초 동안 이동
     */
    private function rcMove(TelloClient $tello, string $droneId, int $lr, int $fb, int $ud, int $yaw, int $cm): array
    {
        $res = $tello->rc($droneId, $lr, $fb, $ud, $yaw);
        if (isset($res['error'])) {
            return $res;
        }
        usleep((int)(($cm / 50) * 1_000_000));
        $tello->rc($droneId, 0, 0, 0, 0);
        usleep(800_000); // 정지 후 0.8초 안정화 대기
        return $res;
    }

    /** POST /api/missions/{id}/confirm — 배송 완료 수동 확인 */
    public function confirm(int $id = 0): \CodeIgniter\HTTP\ResponseInterface
    {
        $mission = $this->missionModel->find($id);
        if (!$mission) {
            return $this->response->setStatusCode(404)
                ->setJSON(['status' => 'error', 'message' => 'not found']);
        }
        if (!in_array($mission['state'], ['arrived', 'flying'], true)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'mission is not in confirmable state']);
        }
        $this->missionModel->setState($id, 'delivered', [
            'delivery_confirmed'    => 1,
            'delivery_confirmed_at' => date('Y-m-d H:i:s'),
            'ended_at'              => date('Y-m-d H:i:s'),
        ]);
        $this->logModel->addLog($mission['drone_id'], 'ok', "미션 #{$id} 배송 완료 수동 확인", $id);
        return $this->response->setJSON(['status' => 'ok']);
    }

    /** PATCH /api/missions/{id}/actions/{actionId} — 액션 결과 갱신 */
    public function updateAction(int $id = 0, int $actionId = 0): \CodeIgniter\HTTP\ResponseInterface
    {
        $action = $this->actionModel->find($actionId);
        if (!$action || (int)$action['mission_id'] !== $id) {
            return $this->response->setStatusCode(404)
                ->setJSON(['status' => 'error', 'message' => 'action not found']);
        }

        $body   = $this->request->getJSON(true) ?? [];
        $result = $body['result'] ?? '';

        if (!in_array($result, ['ok', 'fail', 'skip'], true)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'invalid result']);
        }

        $this->actionModel->setResult($actionId, $result, $body['error'] ?? null);

        return $this->response->setJSON(['status' => 'ok']);
    }
}
