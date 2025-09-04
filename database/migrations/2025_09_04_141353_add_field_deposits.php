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
        Schema::table('deposits', function (Blueprint $table) {
            $table->string('acc_source_num')->nullable()->after('acc_source');
            $table->string('acc_source_name')->nullable()->after('acc_source_num');
            $table->string('acc_destination_num')->nullable()->after('acc_destination');
            $table->string('acc_destination_name')->nullable()->after('acc_destination_num');
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
