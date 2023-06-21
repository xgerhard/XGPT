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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name');
            $table->string('provider');
            $table->string('provider_id');
            $table->tinyInteger('sponsor')->default(0);
            $table->timestamps();
        });

        DB::table('users')->insert([
            'name' => 'default user',
            'display_name' => 'default user',
            'provider' => 'default',
            'provider_id' => 'default',
            'sponsor' => 0
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
