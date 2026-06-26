<?php

namespace App\Models;

use CodeIgniter\Model;

class MissionModel extends Model
{
    protected $table         = 'missions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'drone_id', 'state', 'max_altitude', 'flight_duration', 'fail_reason',
        'delivery_confirmed', 'delivery_confirmed_at', 'started_at', 'ended_at', 'created_at',
    ];

    protected $useTimestamps = false;

    /** 상태 상수 */
    const STATES = ['draft', 'requested', 'preparing', 'flying', 'arrived', 'delivered', 'failed', 'returning'];

    public function getActiveMission(string $droneId): ?array
    {
        return $this->where('drone_id', $droneId)
                    ->whereNotIn('state', ['draft', 'delivered', 'failed'])
                    ->orderBy('id', 'DESC')
                    ->first();
    }

    public function setState(int $id, string $state, array $extra = []): void
    {
        $data = array_merge(['state' => $state], $extra);
        $this->update($id, $data);
    }
}
