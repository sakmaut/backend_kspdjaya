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
            $table->string('ao_id', 100);
            $table->dateTime('visit_date');
            $table->string('tujuan_kredit', 255);
            $table->double('plafond');
            $table->double('tenor');
            $table->string('nama', 255);
            $table->string('ktp', 25);
            $table->string('kk', 25);
            $table->date('tgl_lahir');
            $table->longText('alamat');
            $table->string('hp', 25);
            $table->string('usaha', 255)->nullable();
            $table->string('sector', 255)->nullable();
            $table->string('coordinate', 255)->nullable();
            $table->string('accurate', 255)->nullable();
            $table->tinyInteger('slik');
            $table->string('created_by')->nullable(false);
            $table->timestamp('created_at', 0)->nullable(false)->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('updated_by')->nullable(false);
            $table->timestamp('updated_at', 0)->nullable(false)->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
            $table->string('deleted_by')->nullable(false);
            $table->dateTime('deleted_at')->nullable(true)->default(null);
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
