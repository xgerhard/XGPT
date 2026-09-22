<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A "user" here is a channel: the streamer identity behind a (provider, provider_id) pair
     * (e.g. Twitch/YouTube/Discord, as reported by whichever bot integration parsed the request).
     * It's created lazily on first bot request OR first dashboard login, whichever comes first —
     * there is no email/password auth, only bot-provider OAuth (see NightbotAuthController).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('provider');
            $table->string('provider_id');
            $table->string('token', 8)->unique();
            $table->boolean('sponsor')->default(false);
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
