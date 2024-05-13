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
        Schema::create('cr_prospect_document', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('CR_PROSPECT_ID', 100)->nullable(false);
            $table->string('PATH', 400)->nullable();
            $table->integer('INDEX_NUM')->nullable();
            $table->string('VALID_CHECK', 45)->nullable();
            $table->timestamp('CREATED_AT')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_prospect_document');
    }
};
