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
        Schema::table('application_approval', function (Blueprint $table) {
            $table->string('cr_application_kapos_desc')->after('cr_application_kapos_note')->nullable();
            $table->string('cr_application_ho_desc')->after('cr_application_ho_note')->nullable();
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
