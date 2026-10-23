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
        Schema::create('datecitos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('connection_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('proposer_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('place', 80);
            $table->string('zone', 60);
            $table->string('when_label', 60);
            $table->date('scheduled_on')->nullable();
            $table->string('note', 200)->nullable();
            $table->string('status', 20)->default('proposed');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['connection_id', 'status']);
        });

        Schema::create('datecito_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('datecito_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            // Respuestas privadas, cifradas en reposo.
            $table->text('answers');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['datecito_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datecito_feedback');
        Schema::dropIfExists('datecitos');
    }
};
