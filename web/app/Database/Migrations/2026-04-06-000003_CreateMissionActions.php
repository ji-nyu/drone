<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMissionActions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'mission_id'  => ['type' => 'INT', 'unsigned' => true],
            'seq'         => ['type' => 'TINYINT', 'unsigned' => true],
            'type'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'value'       => ['type' => 'SMALLINT', 'null' => true, 'default' => null],
            'result'      => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'ok', 'fail', 'skip'],
                'default'    => 'pending',
            ],
            'error'       => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true, 'default' => null],
            'executed_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('mission_id');
        $this->forge->createTable('mission_actions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('mission_actions', true);
    }
}
