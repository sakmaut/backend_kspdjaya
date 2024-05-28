<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deuteronomy_transaction_log', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('table_name', 100)->nullable();
            $table->string('table_id', 100)->nullable();
            $table->string('field_name', 100)->nullable();
            $table->string('old_value', 450)->nullable();
            $table->string('new_value', 450)->nullable();
            $table->string('altered_by', 100)->nullable();
            $table->dateTime('altered_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deuteronomy_transaction_log');
    }
};
