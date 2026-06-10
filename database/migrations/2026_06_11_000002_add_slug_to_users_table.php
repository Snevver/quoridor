<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug', 64)->nullable()->after('name');
        });

        DB::table('users')->whereNull('slug')->orderBy('id')->each(function ($user) {
            $base = Str::slug($user->name) ?: 'player';
            $slug = $base;
            $i = 2;
            while (DB::table('users')->where('slug', $slug)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            DB::table('users')->where('id', $user->id)->update(['slug' => $slug]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('slug', 64)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
