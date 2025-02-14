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
        Schema::table('payment_attachment', function (Blueprint $table) {
            $table->string('create_branch')->after('create_position')->nullable();
        });    
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
