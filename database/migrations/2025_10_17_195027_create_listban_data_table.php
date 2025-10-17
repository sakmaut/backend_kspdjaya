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
        Schema::create('listban_data', function (Blueprint $table) {
            $table->uuid('ID')->primary();
            $table->string('KODE')->nullable();
            $table->string('NAMA_CABANG')->nullable();
            $table->string('NO_KONTRAK')->nullable();
            $table->string('CUST_CODE')->nullable();
            $table->string('SUPPLIER')->nullable();
            $table->string('SURVEYOR_ID')->nullable();
            $table->text('CATT_SURVEY')->nullable();
            $table->string('PKK_HUTANG')->nullable();
            $table->string('JUMLAH_ANGSURAN')->nullable();
            $table->string('JARAK_ANGSURAN')->nullable();
            $table->string('PERIOD')->nullable();
            $table->string('OUTSTANDING')->nullable();
            $table->string('OS_BUNGA')->nullable();
            $table->string('OVERDUE_AWAL')->nullable();
            $table->string('AMBC_PKK_AWAL')->nullable();
            $table->string('AMBC_BNG_AWAL')->nullable();
            $table->string('AMBC_TOTAL_AWAL')->nullable();
            $table->string('CYCLE_AWAL')->nullable();
            $table->string('STATUS_BEBAN')->nullable();
            $table->string('POLA_BAYAR')->nullable();
            $table->string('OS_PKK_AKHIR')->nullable();
            $table->string('OS_BNG_AKHIR')->nullable();
            $table->string('OVERDUE_AKHIR')->nullable();
            $table->string('INSTALLMENT')->nullable();
            $table->string('LAST_INST')->nullable();
            $table->string('TIPE')->nullable();
            $table->string('F_ARR_CR_SCHEDL')->nullable();
            $table->string('CURR_ARR')->nullable();
            $table->string('LAST_PAY')->nullable();
            $table->string('COLLECTOR')->nullable();
            $table->string('CARA_BAYAR')->nullable();
            $table->string('AMBC_PKK_AKHIR')->nullable();
            $table->string('AMBC_BNG_AKHIR')->nullable();
            $table->string('AMBC_TOTAL_AKHIR')->nullable();
            $table->string('AC_PKK')->nullable();
            $table->string('AC_BNG_MRG')->nullable();
            $table->string('AC_TOTAL')->nullable();
            $table->string('CYCLE_AKHIR')->nullable();
            $table->string('POLA_BAYAR_AKHIR')->nullable();
            $table->string('JENIS_JAMINAN')->nullable();
            $table->string('NILAI_PINJAMAN')->nullable();
            $table->string('TOTAL_ADMIN')->nullable();
            $table->string('CREATED_BY')->nullable();
            $table->timestamp('CREATED_AT')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listban_data');
    }
};
