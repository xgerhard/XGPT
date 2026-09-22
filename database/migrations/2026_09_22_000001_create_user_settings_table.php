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
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('instructions')->nullable();
            // Provider the channel has opted into (must be one of config('xgpt.enabled_providers')
            // at read time — a value that's since been removed from the allowlist falls back to
            // the free tier rather than erroring, see App\Ai\Agents\ChatResponder).
            $table->string('ai_provider')->nullable();
            // Encrypted (Crypt::encryptString) via the UserSettings model's accessor/mutator.
            $table->text('ai_api_key')->nullable();
            $table->boolean('mention_user')->default(true);
            $table->boolean('show_sponsor_heart')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
