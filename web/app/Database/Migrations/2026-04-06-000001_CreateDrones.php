<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDrones extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'drone_id'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'ip'          => ['type' => 'VARCHAR', 'constraint' => 15],
            'label'       => ['type' => 'VARCHAR', 'constraint' => 50],
            'model'       => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'Tello TT'],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'last_seen_at'=> ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'created_at'  => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('drone_id');
        $this->forge->addUniqueKey('ip');
        $this->forge->createTable('drones', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('drones', true);
    }
}
