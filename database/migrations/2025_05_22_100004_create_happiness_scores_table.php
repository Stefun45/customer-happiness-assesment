<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('happiness_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->float('score');
            $table->enum('churn_risk', ['low', 'medium', 'high']);
            $table->text('analysis_summary');
            $table->json('key_concerns');
            $table->json('recommended_actions');
            $table->timestamp('scored_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('happiness_scores');
    }
};
