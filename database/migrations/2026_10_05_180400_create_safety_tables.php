<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reportes, contactos de confianza y Datecito Seguro.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('reported_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 60);
            // Cifrado en reposo.
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignUlid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('reported_id');
        });

        Schema::create('trusted_contacts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            // Nombre y teléfono cifrados en reposo.
            $table->text('name');
            $table->text('phone');
            $table->timestamps();
        });

        Schema::create('safe_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('datecito_id')->constrained()->cascadeOnDelete();
            $table->json('contact_ids');
            $table->timestamp('check_in_at');
            $table->boolean('share_location')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamp('help_requested_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'check_in_at']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safe_sessions');
        Schema::dropIfExists('trusted_contacts');
        Schema::dropIfExists('reports');
    }
};
