<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces the old conversations/conversation_messages tables. There's no conversation
     * entity any more — every channel has one rolling history, windowed at read time to the
     * last 5 minutes / 10 messages (see App\Ai\Agents\ChatResponder::messages()). `username` is
     * the chatter's display name for `role = user` rows (formatted "[username] message" when fed
     * to the model) and is null for `role = assistant` rows (single bot identity).
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 25);
            $table->string('username')->nullable();
            $table->text('content');
            $table->timestamps();

            $table->index(['channel_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
