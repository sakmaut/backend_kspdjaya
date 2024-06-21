<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_rolling', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('users_id')->nullable();
            $table->string('position')->nullable();
            $table->string('branch')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent(); 
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('hr_rolling');
    }
};
