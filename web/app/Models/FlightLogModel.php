<?php

namespace App\Models;

use CodeIgniter\Model;

class FlightLogModel extends Model
{
    protected $table         = 'flight_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'drone_id', 'mission_id', 'level', 'message', 'logged_at',
    ];

    protected $useTimestamps = false;

    public function addLog(string $droneId, string $level, string $message, ?int $missionId = null): void
    {
        $this->insert([
            'drone_id'   => $droneId,
            'mission_id' => $missionId,
            'level'      => $level,
            'message'    => $message,
            'logged_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function getRecent(string $droneId = '', string $level = '', int $limit = 100, int $offset = 0): array
    {
        $q = $this->orderBy('logged_at', 'DESC')->limit($limit, $offset);
        if ($droneId) {
            $q->where('drone_id', $droneId);
        }
        if ($level) {
            $q->where('level', $level);
        }
        return $q->findAll();
    }
}
