<?php

namespace App\Models;

use CodeIgniter\Model;

class DroneTelemetryModel extends Model
{
    protected $table         = 'drone_telemetry';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'drone_id', 'battery', 'altitude', 'speed', 'temperature', 'yaw',
        'recorded_at',
    ];

    protected $useTimestamps = false;

    public function getLatest(string $droneId): ?array
    {
        return $this->where('drone_id', $droneId)
                    ->orderBy('recorded_at', 'DESC')
                    ->first();
    }

    /** 30초 이내 telemetry 가 있으면 online */
    public function isOnline(string $droneId): bool
    {
        $cutoff = date('Y-m-d H:i:s', time() - 30);
        return $this->where('drone_id', $droneId)
                    ->where('recorded_at >=', $cutoff)
                    ->countAllResults() > 0;
    }

    public function push(string $droneId, array $data): void
    {
        $this->insert(array_merge(
            ['drone_id' => $droneId, 'recorded_at' => date('Y-m-d H:i:s')],
            $data
        ));
    }
}
