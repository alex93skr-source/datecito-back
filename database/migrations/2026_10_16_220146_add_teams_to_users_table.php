<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Equipos a los que le va la persona, por deporte: {"Fútbol": ["Pumas"], "Béisbol": ["Diablos Rojos del México"]}.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('teams')->nullable()->after('interests');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('teams');
        });
    }
};
