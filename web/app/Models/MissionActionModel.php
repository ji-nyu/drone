<?php

namespace App\Models;

use CodeIgniter\Model;

class MissionActionModel extends Model
{
    protected $table         = 'mission_actions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'mission_id', 'seq', 'type', 'value', 'result', 'error', 'executed_at',
    ];

    protected $useTimestamps = false;

    public function getByMission(int $missionId): array
    {
        return $this->where('mission_id', $missionId)
                    ->orderBy('seq', 'ASC')
                    ->findAll();
    }

    public function insertActions(int $missionId, array $actions): void
    {
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
            parent::insertBatch($rows);
        }
    }

    public function setResult(int $id, string $result, ?string $error = null): void
    {
        $this->update($id, [
            'result'      => $result,
            'error'       => $error,
            'executed_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
