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
        Schema::create('tic_ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_id')->nullable(false);
            $table->text('messages')->nullable();
            $table->text('file_path')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('ticket_id')->references('id')->on('tic_tickets')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tic_ticket_messages');
    }
};
