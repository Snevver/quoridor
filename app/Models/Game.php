<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Game extends Model
{
    protected $fillable = [
        'slug',
        'player1_id',
        'player2_id',
        'board_state',
        'current_turn',
        'status',
        'winner_id',
        'move_count',
        'p1_elo_before',
        'p2_elo_before',
        'p1_elo_after',
        'p2_elo_after',
    ];

    protected $casts = [
        'board_state' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Game $game) {
            while (!$game->slug) {
                $candidate = Str::lower(Str::random(10));
                if (!static::where('slug', $candidate)->exists()) {
                    $game->slug = $candidate;
                }
            }
        });
    }

    /** Games are addressed by slug in URLs, never by numeric id. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Monotonic state counter so clients can discard stale payloads when the
     * websocket and the HTTP poll race. Finished games purge their move rows
     * and keep only move_count.
     */
    public function getVersionAttribute(): int
    {
        return $this->status === 'finished'
            ? max((int) $this->move_count, $this->moves()->count())
            : $this->moves()->count();
    }

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
