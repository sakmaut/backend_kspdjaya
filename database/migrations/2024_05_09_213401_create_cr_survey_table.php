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
            $table->char('id')->primary();
            $table->string('branch_id')->nullable();
            $table->datetime('visit_date')->nullable()->default(null);
            $table->string('tujuan_kredit')->charset('utf8')->nullable();
            $table->double('plafond')->nullable();
            $table->double('tenor')->nullable();
            $table->string('category')->nullable();
            $table->string('nama')->nullable();
            $table->date('tgl_lahir')->nullable()->default(null);
            $table->string('ktp')->nullable();
            $table->string('hp')->nullable();
            $table->longtext('alamat')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('work_period')->nullable();
            $table->string('income_personal')->nullable();
            $table->string('income_spouse')->nullable();         
            $table->string('income_other')->nullable();
            $table->string('usaha')->nullable();
            $table->string('sector')->nullable();
            $table->string('expenses')->nullable();
            $table->string('survey_note')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at', 0)->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at', 0)->nullable()->default(null);;
            $table->string('deleted_by')->nullable();
            $table->timestamp('deleted_at')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_prospect');
    }
};
