<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->boolean('use_history')->default(true)->after('show_sponsor_heart');
            // Own-key channels only; null means the site default (config('xgpt.history')).
            $table->unsignedSmallInteger('history_max_messages')->nullable()->after('use_history');
            $table->unsignedSmallInteger('history_window_minutes')->nullable()->after('history_max_messages');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn(['use_history', 'history_max_messages', 'history_window_minutes']);
        });
    }
};
