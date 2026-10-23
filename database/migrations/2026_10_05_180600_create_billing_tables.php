<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membresías y compras únicas (paquetes de Super likes).
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 20);
            $table->string('store', 20);
            $table->string('store_reference')->unique();
            $table->string('status', 20)->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'status', 'expires_at']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_id', 60);
            $table->string('store', 20);
            // Evita acreditar dos veces la misma compra.
            $table->string('store_reference')->unique();
            $table->unsignedInteger('quantity');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('subscriptions');
    }
};
