<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDroneTelemetry extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'drone_id'      => ['type' => 'VARCHAR', 'constraint' => 20],
            'battery'       => ['type' => 'TINYINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'altitude'      => ['type' => 'SMALLINT', 'null' => true, 'default' => null],
            'speed'         => ['type' => 'SMALLINT', 'null' => true, 'default' => null],
            'temperature'   => ['type' => 'SMALLINT', 'null' => true, 'default' => null],
            'yaw'           => ['type' => 'SMALLINT', 'null' => true, 'default' => null],
            'mission_state' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null],
            'recorded_at'   => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('drone_id');
        $this->forge->addKey('recorded_at');
        $this->forge->createTable('drone_telemetry', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('drone_telemetry', true);
    }
}
