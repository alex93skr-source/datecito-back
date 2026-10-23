<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Aquí y ahora": sesiones temporales en un lugar.
     */
    public function up(): void
    {
        Schema::create('here_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('place', 80);
            // Coordenadas redondeadas (3 decimales ≈ 100 m). Nunca se exponen.
            $table->decimal('lat', 6, 3);
            $table->decimal('lng', 6, 3);
            $table->boolean('verified_only')->default(true);
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->index(['ends_at', 'lat', 'lng']);
            $table->index('user_id');
        });

        Schema::create('here_greets', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('target_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'target_id']);
        });

        Schema::create('here_hidden', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('hidden_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'hidden_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('here_hidden');
        Schema::dropIfExists('here_greets');
        Schema::dropIfExists('here_sessions');
    }
};
