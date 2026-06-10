<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('slug', 16)->nullable()->after('id');
        });

        DB::table('games')->whereNull('slug')->orderBy('id')->each(function ($game) {
            DB::table('games')->where('id', $game->id)->update(['slug' => Str::lower(Str::random(10))]);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->string('slug', 16)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
