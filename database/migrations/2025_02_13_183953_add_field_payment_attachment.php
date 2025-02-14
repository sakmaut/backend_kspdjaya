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
            $table->string('create_by')->after('file_attach')->nullable();
            $table->string('create_position')->after('create_by')->nullable();
            $table->timestamp('create_date')->after('create_position')->nullable();
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
