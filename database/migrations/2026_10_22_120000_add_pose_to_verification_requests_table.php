<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pose al azar que se pidió en la selfie: quien sube fotos ajenas no tiene una con esa pose.
     */
    public function up(): void
    {
        Schema::table('verification_requests', function (Blueprint $table) {
            $table->string('pose', 80)->nullable()->after('selfie_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verification_requests', function (Blueprint $table) {
            $table->dropColumn('pose');
        });
    }
};
