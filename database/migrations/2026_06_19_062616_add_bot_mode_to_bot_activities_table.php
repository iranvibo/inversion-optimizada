<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_activities', function (Blueprint $table) {
            $table->string('bot_mode')->default('real')->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_activities', function (Blueprint $table) {
            $table->dropColumn('bot_mode');
        });
    }
};
