<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Intervalo elegido para el "¿todo bien?": cada check-in vuelve a programarse con él.
     */
    public function up(): void
    {
        Schema::table('safe_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('check_in_minutes')->default(60)->after('check_in_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('safe_sessions', function (Blueprint $table) {
            $table->dropColumn('check_in_minutes');
        });
    }
};
