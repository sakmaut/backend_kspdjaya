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
        Schema::table('application_approval_log', function (Blueprint $table) {
            $table->string('CODE')->nullable()->after('ID');
            $table->string('POSITION')->nullable()->after('CODE');
            $table->text('ONCHARGE_DESCR')->nullable()->change();
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
