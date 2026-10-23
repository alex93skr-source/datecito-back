<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mensajes enviados desde los formularios del sitio web (contacto, alianzas,
     * boletín, solicitudes ARCO, lista de espera). Se revisan en el CRM.
     */
    public function up(): void
    {
        Schema::create('web_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('kind', 20);
            $table->string('lang', 2)->default('es');
            $table->string('name', 120)->nullable();
            $table->string('email', 160);
            $table->string('topic', 80)->nullable();
            $table->string('company', 120)->nullable();
            $table->string('city', 80)->nullable();
            // Cifrado en reposo: puede contener datos personales.
            $table->text('message')->nullable();
            // Identificación adjunta en solicitudes ARCO (almacenamiento privado).
            $table->string('attachment_key')->nullable();
            $table->string('status', 20)->default('new');
            $table->foreignUlid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            // Hash de la IP (no la IP) para detectar abuso sin guardar datos de más.
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['kind', 'status', 'created_at']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_messages');
    }
};
