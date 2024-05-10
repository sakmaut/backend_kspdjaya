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
        Schema::create('master_role_access_menu', function (Blueprint $table) {
            $table->char('id', 45)->primary();
            $table->string('master_menu_id', 100);
            $table->string('master_role_id', 100);
            $table->string('created_by', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 45)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('deleted_by', 45)->nullable();
            $table->dateTime('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_role_access_menu');
    }
};
