<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Likes, matches (conexiones), mensajes y bloqueos.
     */
    public function up(): void
    {
        Schema::create('swipes', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('target_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 10);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'target_id']);
            $table->index(['target_id', 'type']);
            $table->index(['user_id', 'type', 'created_at']);
        });

        Schema::create('connections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Siempre user_one_id < user_two_id para que el par sea único.
            $table->foreignUlid('user_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('user_two_id')->constrained('users')->cascadeOnDelete();
            $table->string('source', 20)->default('discover');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignUlid('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_one_id', 'user_two_id']);
            $table->index('user_two_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('connection_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('sender_id')->constrained('users')->cascadeOnDelete();
            // Cifrado en reposo con la llave de la aplicación.
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['connection_id', 'id']);
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('connections');
        Schema::dropIfExists('swipes');
    }
};
