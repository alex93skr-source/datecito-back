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
        Schema::create('users', function (Blueprint $table) {
            // ULID: IDs no secuenciales para que nadie pueda recorrer usuarios.
            $table->ulid('id')->primary();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            $table->string('role')->default('user');
            $table->string('status')->default('active');

            // Perfil
            $table->string('name', 40)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('gender', 40)->nullable();
            $table->json('interested_in')->nullable();
            $table->string('job', 60)->nullable();
            $table->string('bio', 300)->nullable();
            $table->string('intent', 60)->nullable();
            $table->string('relationship_type', 40)->nullable();
            $table->string('zone', 60)->nullable();
            $table->json('interests')->nullable();
            $table->json('music_genres')->nullable();
            $table->json('music_artists')->nullable();
            $table->string('anthem', 120)->nullable();
            $table->json('basics')->nullable();
            $table->json('languages')->nullable();
            $table->json('prompts')->nullable();

            // Ubicación aproximada (2 decimales ≈ 1 km). Nunca se expone.
            $table->decimal('approx_lat', 5, 2)->nullable();
            $table->decimal('approx_lng', 5, 2)->nullable();

            // Preferencias de privacidad y seguridad
            $table->boolean('show_in_discover')->default(true);
            $table->boolean('show_gender')->default(false);
            $table->boolean('show_job')->default(true);
            $table->boolean('show_relationship')->default(false);
            $table->boolean('only_verified_messages')->default(false);
            $table->boolean('i_write_first')->default(false);
            $table->boolean('offensive_warning')->default(true);
            $table->json('filters')->nullable();

            $table->string('verification_status')->default('none');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('super_likes_balance')->default(0);

            $table->timestamps();

            $table->index(['status', 'show_in_discover']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
