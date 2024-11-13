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
            $table->string('code')->nullable()->after('id');
            $table->text('cr_application_kapos_desc')->nullable()->change();
            $table->text('cr_application_ho_desc')->nullable()->change();
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
