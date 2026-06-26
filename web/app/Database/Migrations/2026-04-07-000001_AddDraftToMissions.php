<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDraftToMissions extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE missions MODIFY state ENUM('draft','requested','preparing','flying','arrived','delivered','failed','returning') NOT NULL DEFAULT 'draft'"
        );
    }

    public function down(): void
    {
        $this->db->query(
            "ALTER TABLE missions MODIFY state ENUM('requested','preparing','flying','arrived','delivered','failed','returning') NOT NULL DEFAULT 'requested'"
        );
    }
}
