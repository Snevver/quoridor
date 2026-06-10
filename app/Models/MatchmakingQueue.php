<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchmakingQueue extends Model
{
    protected $table = 'matchmaking_queue';

    protected $fillable = [
        'user_id',
        'elo_at_join',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
