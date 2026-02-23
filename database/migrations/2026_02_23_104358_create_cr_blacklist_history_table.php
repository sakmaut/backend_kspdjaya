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
        Schema::create('cr_blacklist_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cr_blacklist_id')->nullable();
            $table->string('status')->nullable();
            $table->text('reason')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_blacklist_history');
    }
};
