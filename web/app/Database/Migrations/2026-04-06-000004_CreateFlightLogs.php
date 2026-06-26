<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFlightLogs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'drone_id'   => ['type' => 'VARCHAR', 'constraint' => 20],
            'mission_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'level'      => [
                'type'       => 'ENUM',
                'constraint' => ['info', 'warn', 'error', 'ok'],
                'default'    => 'info',
            ],
            'message'    => ['type' => 'VARCHAR', 'constraint' => 500],
            'logged_at'  => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('drone_id');
        $this->forge->addKey('mission_id');
        $this->forge->addKey('logged_at');
        $this->forge->createTable('flight_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('flight_logs', true);
    }
}
