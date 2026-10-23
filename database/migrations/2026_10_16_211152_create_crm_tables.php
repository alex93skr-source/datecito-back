<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas del CRM: eventos de comportamiento, bitácora de administración,
     * notas y etiquetas internas sobre personas, y última actividad.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->index()->after('super_likes_balance');
            $table->string('last_platform', 20)->nullable()->after('last_seen_at');
            $table->string('last_app_version', 20)->nullable()->after('last_platform');
            $table->string('signup_platform', 20)->nullable()->after('last_app_version');

            $table->index('created_at');
            $table->index('role');
        });

        // Cada acción relevante (API y app). Nunca guarda contenido privado: ni mensajes ni contraseñas.
        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->string('category', 30);
            $table->json('properties')->nullable();
            $table->string('platform', 20)->nullable();
            $table->string('app_version', 20)->nullable();
            // Sólo un hash de la IP, para detectar abuso sin guardar la IP.
            $table->string('ip_hash', 64)->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['name', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['category', 'occurred_at']);
            $table->index('occurred_at');
        });

        // Bitácora: quién del equipo hizo qué, sobre quién y cuándo.
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->string('subject_id', 40)->nullable();
            $table->json('changes')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('user_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('author_id')->nullable()->constrained('users')->nullOnDelete();
            // Cifrado en reposo.
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'pinned', 'created_at']);
        });

        Schema::create('user_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('tag', 40);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'tag']);
            $table->index('tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tags');
        Schema::dropIfExists('user_notes');
        Schema::dropIfExists('admin_actions');
        Schema::dropIfExists('activity_events');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['role']);
            $table->dropColumn(['last_seen_at', 'last_platform', 'last_app_version', 'signup_platform']);
        });
    }
};
