<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class);
            $table->text('api_key')->nullable();
            $table->text('start_instructions')->nullable();
            $table->text('end_instructions')->nullable();
            $table->tinyInteger('show_conversation_id')->default(1);
            $table->tinyInteger('mention_user')->default(1);
            $table->tinyInteger('show_sponsor_heart')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
