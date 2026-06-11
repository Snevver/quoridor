<?php

use App\Models\Game;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('online', fn ($user) => ['id' => $user->id, 'name' => $user->name]);

// Presence channel: members are exposed so each client can see when the
// opponent connects or drops.
Broadcast::channel('game.{gameId}', function ($user, $gameId) {
    $game = Game::find($gameId);

    if (!$game || !$game->hasPlayer($user)) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});
