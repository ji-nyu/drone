<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropMissionStateFromTelemetry extends Migration
{
    public function up(): void
    {
        $this->forge->dropColumn('drone_telemetry', 'mission_state');
    }

    public function down(): void
    {
        $this->forge->addColumn('drone_telemetry', [
            'mission_state' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'default'    => null,
                'after'      => 'yaw',
            ],
        ]);
    }
}
