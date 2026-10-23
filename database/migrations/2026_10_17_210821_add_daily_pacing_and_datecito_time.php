<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - users.free_super_like_at: cuándo usó su último Super like gratis (hay uno cada N días).
     * - datecitos.scheduled_at: día y hora exactos elegidos en el calendario.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('free_super_like_at')->nullable()->after('super_likes_balance');
        });

        Schema::table('datecitos', function (Blueprint $table) {
            $table->dateTime('scheduled_at')->nullable()->after('scheduled_on');
            $table->index(['connection_id', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('datecitos', function (Blueprint $table) {
            $table->dropIndex(['connection_id', 'scheduled_at']);
            $table->dropColumn('scheduled_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('free_super_like_at');
        });
    }
};
