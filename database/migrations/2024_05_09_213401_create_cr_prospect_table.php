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
        Schema::create('cr_prospect', function (Blueprint $table) {
            $table->char('id', 100)->primary();
            $table->string('ao_id', 10)->nullable(false);
            $table->string('branch_id',100)->nullable();
            $table->datetime('visit_date')->nullable()->default(null);
            $table->string('mother_name', 45)->nullable();
            $table->string('category', 45)->nullable();
            $table->string('tin_number', 45)->nullable();
            $table->string('title', 45)->nullable();
            $table->string('work_period', 45)->nullable();
            $table->string('dependants', 45)->nullable();
            $table->decimal('income_personal', 20, 2)->nullable();
            $table->decimal('income_spouse', 20, 2)->nullable();
            $table->decimal('income_other', 20, 2)->nullable();
            $table->decimal('expenses', 20, 2)->nullable();
            $table->string('cust_code_ref', 100)->nullable();
            $table->string('tujuan_kredit', 255)->charset('utf8')->nullable();
            $table->string('jenis_produk', 255)->nullable();
            $table->double('plafond')->nullable();
            $table->double('tenor')->nullable();
            $table->string('nama', 255)->nullable();
            $table->string('ktp', 25)->nullable();
            $table->string('kk', 25)->nullable();
            $table->date('tgl_lahir')->nullable()->default(null);
            $table->longtext('alamat')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('hp', 25)->nullable();
            $table->string('usaha', 255)->nullable();
            $table->string('sector', 255)->nullable();
            $table->string('coordinate', 255)->nullable();
            $table->string('accurate', 255)->nullable();
            $table->string('survey_note', 2000)->nullable();
            $table->string('payment_reference', 45)->nullable();
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
