<?php

namespace App\Models;

use CodeIgniter\Model;

class DroneModel extends Model
{
    protected $table         = 'drones';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'drone_id', 'ip', 'label', 'model', 'is_active', 'last_seen_at',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';

    public function findByDroneId(string $droneId): ?array
    {
        return $this->where('drone_id', $droneId)->where('is_active', 1)->first();
    }

    public function touchLastSeen(string $droneId): void
    {
        $this->where('drone_id', $droneId)
             ->set('last_seen_at', date('Y-m-d H:i:s'))
             ->update();
    }
}
