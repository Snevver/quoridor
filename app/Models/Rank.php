<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    protected $fillable = [
        'name',
        'min_elo',
        'color',
    ];

    public static function forElo(int $elo): ?self
    {
        return static::where('min_elo', '<=', $elo)->orderByDesc('min_elo')->first();
    }
}
