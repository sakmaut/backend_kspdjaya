<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE credit MODIFY CREATED_BY VARCHAR(10) NULL AFTER VERSION');
        DB::statement("ALTER TABLE credit MODIFY CREATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER CREATED_BY");
        DB::statement('ALTER TABLE credit MODIFY DELETED_BY VARCHAR(10) NULL AFTER MOD_USER');
        DB::statement('ALTER TABLE credit MODIFY DELETED_AT TIMESTAMP NULL AFTER DELETED_BY');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
