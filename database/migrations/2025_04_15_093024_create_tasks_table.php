<?php

use Carbon\Carbon;
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
        Schema::create('tasks', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type')->nullable();
            $table->string('type_id')->nullable();
            $table->string('status')->nullable();
            $table->string('descr')->nullable();
            $table->string('recipient_id')->nullable();
            $table->string('created_id')->nullable();
            $table->string('created_by')->nullable();
            $table->string('created_branch')->nullable();
            $table->string('created_position')->nullable();
            $table->dateTime('created_at')->nullable()->default(Carbon::now());
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
