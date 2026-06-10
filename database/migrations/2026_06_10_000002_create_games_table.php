<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player1_id')->constrained('users');
            $table->foreignId('player2_id')->constrained('users');
            $table->json('board_state');
            $table->enum('current_turn', ['p1', 'p2'])->default('p1');
            $table->enum('status', ['waiting', 'active', 'finished'])->default('active');
            $table->foreignId('winner_id')->nullable()->constrained('users');
            $table->integer('p1_elo_before')->nullable();
            $table->integer('p2_elo_before')->nullable();
            $table->integer('p1_elo_after')->nullable();
            $table->integer('p2_elo_after')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
