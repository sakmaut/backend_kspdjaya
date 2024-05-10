<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cr_survey', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('CR_PROSPECT_ID', 100)->nullable();
            $table->string('MOTHER_NAME', 45)->nullable();
            $table->string('CATEGORY', 45)->nullable();
            $table->string('TIN_NUMBER', 45)->nullable();
            $table->string('TITLE', 45)->nullable();
            $table->string('WORK_PERIOD', 45)->nullable();
            $table->string('DEPENDANTS', 45)->nullable();
            $table->decimal('INCOME_PERSONAL', 20, 2)->nullable();
            $table->decimal('INCOME_SPOUSE', 20, 2)->nullable();
            $table->decimal('INCOME_OTHER', 20, 2)->nullable();
            $table->decimal('EXPENSES', 20, 2)->nullable();
            $table->string('CUST_CODE_REF', 100)->nullable();
            $table->string('SURVEYOR_ID', 100)->nullable();
            $table->string('SURVEY_NOTE', 2000)->nullable();
            $table->string('PAYMENT_PREFERENCE', 45)->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at', 0)->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at', 0)->nullable()->default(null);;
            $table->string('deleted_by')->nullable();
            $table->dateTime('deleted_at')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_survey');
    }
};
