<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('min_elo')->unique();
            $table->string('color', 9)->default('#6b7394');
            $table->timestamps();
        });

        DB::table('ranks')->insert([
            ['name' => 'Bronze', 'min_elo' => 0, 'color' => '#cd7f32', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Silver', 'min_elo' => 1150, 'color' => '#c0c4ce', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gold', 'min_elo' => 1300, 'color' => '#fbbf24', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Diamond', 'min_elo' => 1400, 'color' => '#7dd3fc', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Master', 'min_elo' => 1600, 'color' => '#c084fc', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Grandmaster', 'min_elo' => 1800, 'color' => '#fb4d6d', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ranks');
    }
};
