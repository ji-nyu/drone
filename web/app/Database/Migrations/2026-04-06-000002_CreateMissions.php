<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMissions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'drone_id'               => ['type' => 'VARCHAR', 'constraint' => 20],
            'state'                  => [
                'type'       => 'ENUM',
                'constraint' => ['requested', 'preparing', 'flying', 'arrived', 'delivered', 'failed', 'returning'],
                'default'    => 'requested',
            ],
            'max_altitude'           => ['type' => 'SMALLINT', 'null' => true, 'default' => null],
            'flight_duration'        => ['type' => 'INT', 'null' => true, 'default' => null],
            'fail_reason'            => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true, 'default' => null],
            'delivery_confirmed'     => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true, 'default' => null],
            'delivery_confirmed_at'  => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'started_at'             => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'ended_at'               => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'created_at'             => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('drone_id');
        $this->forge->addKey('state');
        $this->forge->createTable('missions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('missions', true);
    }
}
