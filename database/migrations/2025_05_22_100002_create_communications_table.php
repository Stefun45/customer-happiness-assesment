<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->enum('source', ['freshdesk', 'fireflies', 'onboarding_helpdesk']);
            $table->string('source_id');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->timestamp('occurred_at');
            $table->float('sentiment_score')->nullable();
            $table->json('raw_payload');
            $table->timestamps();

            $table->unique(['source', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
