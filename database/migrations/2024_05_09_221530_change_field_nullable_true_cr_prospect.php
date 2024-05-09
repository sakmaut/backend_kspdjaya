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
        Schema::table('cr_prospect', function (Blueprint $table) {
            $table->string('created_by')->nullable(true)->change();
            $table->string('updated_by')->nullable(true)->change();
            $table->string('deleted_by')->nullable(true)->change();
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
