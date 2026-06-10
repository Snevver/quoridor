<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'player1_id',
        'player2_id',
        'board_state',
        'current_turn',
        'status',
        'winner_id',
        'p1_elo_before',
        'p2_elo_before',
        'p1_elo_after',
        'p2_elo_after',
    ];

    protected $casts = [
        'board_state' => 'array',
    ];

    public function player1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player2_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(GameMove::class);
    }

    public function hasPlayer(User $user): bool
    {
        return $this->player1_id === $user->id || $this->player2_id === $user->id;
    }

    public function roleOf(User $user): ?string
    {
        return match ($user->id) {
            $this->player1_id => 'p1',
            $this->player2_id => 'p2',
            default => null,
        };
    }
}
