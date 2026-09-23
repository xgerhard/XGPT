<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            // Optional cap on own-key replies per UTC day; null means no limit.
            $table->unsignedInteger('daily_request_limit')->nullable()->after('ai_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn('daily_request_limit');
        });
    }
};
