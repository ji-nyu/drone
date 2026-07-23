<?php

namespace App\Models;

use CodeIgniter\Model;

class MarineZoneModel extends Model
{
    protected $table         = 'marine_zones';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'zone_id', 'zone_name', 'beach_name', 'center_lat', 'center_lng',
        'polygon_geojson', 'risk', 'count', 'level', 'is_active'
    ];

    protected $useTimestamps = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';

    public function ensureTable(): void
    {
        $db = $this->db;

        if ($db->DBDriver === 'SQLite3') {
            $db->query("CREATE TABLE IF NOT EXISTS marine_zones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                zone_id TEXT UNIQUE,
                zone_name TEXT NOT NULL,
                beach_name TEXT DEFAULT '함덕 해수욕장',
                center_lat REAL,
                center_lng REAL,
                polygon_geojson TEXT NOT NULL,
                risk INTEGER DEFAULT 0,
                count INTEGER DEFAULT 0,
                level TEXT DEFAULT 'normal',
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            return;
        }

        $db->query("CREATE TABLE IF NOT EXISTS marine_zones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            zone_id VARCHAR(100) UNIQUE,
            zone_name VARCHAR(255) NOT NULL,
            beach_name VARCHAR(255) DEFAULT '함덕 해수욕장',
            center_lat DOUBLE,
            center_lng DOUBLE,
            polygon_geojson LONGTEXT NOT NULL,
            risk INT DEFAULT 0,
            count INT DEFAULT 0,
            level VARCHAR(30) DEFAULT 'normal',
            is_active TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
