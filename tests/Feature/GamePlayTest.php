<?php

namespace Tests\Feature;

use App\Exceptions\InvalidMoveException;
use App\Models\Game;
use App\Models\User;
use App\Services\EloService;
use App\Services\GameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePlayTest extends TestCase
{
    use RefreshDatabase;

    private GameService $service;
    private User $alice;
    private User $bob;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GameService::class);
        $this->alice = User::factory()->create(['elo' => 1200]);
        $this->bob = User::factory()->create(['elo' => 1200]);

        $this->game = Game::create([
            'player1_id' => $this->alice->id,
            'player2_id' => $this->bob->id,
            'board_state' => GameService::initialBoardState(),
            'current_turn' => 'p1',
            'status' => 'active',
            'p1_elo_before' => $this->alice->elo,
            'p2_elo_before' => $this->bob->elo,
        ]);
    }

    private function pawn(User $user, int $x, int $y): array
    {
        return $this->service->applyMove($this->game, $user, ['move_type' => 'pawn', 'to' => [$x, $y]]);
    }

    public function test_moving_out_of_turn_is_rejected(): void
    {
        $this->expectException(InvalidMoveException::class);

        $this->pawn($this->bob, 4, 7);
    }

    public function test_wall_move_decrements_wall_count_and_is_recorded(): void
    {
        $state = $this->service->applyMove($this->game, $this->alice, [
            'move_type' => 'wall', 'x' => 4, 'y' => 4, 'orientation' => 'H',
        ]);

        $this->assertSame(9, $state['walls_left']['p1']);
        $this->assertSame('p2', $state['current_turn']);
        $this->assertCount(1, $state['walls']);
        $this->assertDatabaseHas('game_moves', [
            'game_id' => $this->game->id,
            'player_id' => $this->alice->id,
            'move_type' => 'wall',
            'move_number' => 1,
        ]);
    }

    public function test_illegal_wall_does_not_change_state(): void
    {
        try {
            $this->service->applyMove($this->game, $this->alice, [
                'move_type' => 'wall', 'x' => 8, 'y' => 4, 'orientation' => 'H',
            ]);
            $this->fail('Expected InvalidMoveException');
        } catch (InvalidMoveException) {
        }

        $this->game->refresh();
        $this->assertSame(10, $this->game->board_state['walls_left']['p1']);
        $this->assertSame('p1', $this->game->board_state['current_turn']);
    }

    public function test_full_game_updates_winner_stats_and_elo(): void
    {
        // Alice (p1) walks straight down column 4 while Bob (p2) shuffles
        // around columns 3 of his back rows.
        $bobSpots = [[3, 8], [3, 7], [3, 8], [3, 7], [3, 8], [3, 7], [3, 8]];

        for ($i = 1; $i <= 7; $i++) {
            $this->pawn($this->alice, 4, $i);
            $this->pawn($this->bob, ...$bobSpots[$i - 1]);
        }

        $state = $this->pawn($this->alice, 4, 8); // reaches Bob's back row

        $this->assertSame('finished', $state['status']);
        $this->assertSame('p1', $state['winner']);

        $this->game->refresh();
        $this->alice->refresh();
        $this->bob->refresh();

        $this->assertSame('finished', $this->game->status);
        $this->assertSame($this->alice->id, $this->game->winner_id);
        $this->assertSame(1216, $this->alice->elo);
        $this->assertSame(1184, $this->bob->elo);
        $this->assertSame(1216, $this->game->p1_elo_after);
        $this->assertSame(1184, $this->game->p2_elo_after);
        $this->assertSame(1, $this->alice->games_played);
        $this->assertSame(1, $this->alice->games_won);
        $this->assertSame(1, $this->bob->games_played);
        $this->assertSame(0, $this->bob->games_won);

        // Only the move total survives — the moves themselves are purged.
        $this->assertSame(15, $this->game->move_count);
        $this->assertDatabaseCount('game_moves', 0);
    }

    public function test_no_moves_accepted_after_game_ends(): void
    {
        $bobSpots = [[3, 8], [3, 7], [3, 8], [3, 7], [3, 8], [3, 7], [3, 8]];
        for ($i = 1; $i <= 7; $i++) {
            $this->pawn($this->alice, 4, $i);
            $this->pawn($this->bob, ...$bobSpots[$i - 1]);
        }
        $this->pawn($this->alice, 4, 8);

        $this->expectException(InvalidMoveException::class);
        $this->pawn($this->bob, 3, 7);
    }

    public function test_elo_calculation_favors_underdog(): void
    {
        $elo = new EloService();

        $this->assertSame(['a' => 1216, 'b' => 1184], $elo->calculate(1200, 1200, 'a_wins'));

        // An underdog win transfers more points than a favorite win.
        $underdog = $elo->calculate(1000, 1400, 'a_wins');
        $this->assertGreaterThan(1024, $underdog['a']);
        $this->assertLessThan(1376, $underdog['b']);
    }
}
