<?php

namespace App\Events;

use App\Models\Game;
use App\Models\GameMove;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Game $game, public ?GameMove $move = null)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('game.'.$this->game->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'board_state' => $this->game->board_state,
            'version' => $this->game->version,
            'last_move' => $this->move ? [
                'player_id' => $this->move->player_id,
                'move_type' => $this->move->move_type,
                'payload' => $this->move->payload,
            ] : null,
            'elo' => $this->game->status === 'finished' ? [
                'p1_before' => $this->game->p1_elo_before,
                'p1_after' => $this->game->p1_elo_after,
                'p2_before' => $this->game->p2_elo_before,
                'p2_after' => $this->game->p2_elo_after,
            ] : null,
        ];
    }
}
