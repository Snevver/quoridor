<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedInteger('move_count')->default(0)->after('winner_id');
        });

        // Finished games keep only their move total; the moves themselves
        // are history we no longer need. Live games keep their moves until
        // they finish.
        DB::table('games')->where('status', 'finished')->orderBy('id')->each(function ($game) {
            DB::table('games')->where('id', $game->id)->update([
                'move_count' => DB::table('game_moves')->where('game_id', $game->id)->count(),
            ]);
        });

        DB::table('game_moves')
            ->whereIn('game_id', DB::table('games')->where('status', 'finished')->pluck('id'))
            ->delete();
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('move_count');
        });
    }
};
