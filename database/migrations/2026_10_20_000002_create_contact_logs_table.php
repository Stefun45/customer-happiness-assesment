<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A completed scheduled contact with a client (call, meeting, check-in)
        Schema::create('contact_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('communication_id')->nullable()->constrained()->nullOnDelete();
            $table->date('contacted_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'contacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_logs');
    }
};
