<?php

namespace Tests\Unit;

use App\Services\EloService;
use App\Services\GameService;
use PHPUnit\Framework\TestCase;

class GameServicePawnTest extends TestCase
{
    private GameService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GameService(new EloService());
    }

    private function state(array $overrides = []): array
    {
        return array_replace_recursive(GameService::initialBoardState(), $overrides);
    }

    private function assertHasMove(array $moves, int $x, int $y): void
    {
        $this->assertContains(['x' => $x, 'y' => $y], $moves);
    }

    private function assertNotHasMove(array $moves, int $x, int $y): void
    {
        $this->assertNotContains(['x' => $x, 'y' => $y], $moves);
    }

    public function test_initial_legal_moves_for_p1(): void
    {
        $moves = $this->service->getLegalMoves($this->state(), 'p1');

        $this->assertCount(3, $moves);
        $this->assertHasMove($moves, 3, 0);
        $this->assertHasMove($moves, 5, 0);
        $this->assertHasMove($moves, 4, 1);
    }

    public function test_pawn_cannot_move_two_cells_or_diagonally(): void
    {
        $state = $this->state();

        $this->assertFalse($this->service->validatePawnMove($state, 'p1', 4, 2));
        $this->assertFalse($this->service->validatePawnMove($state, 'p1', 3, 1));
        $this->assertFalse($this->service->validatePawnMove($state, 'p1', 4, 0));
    }

    public function test_horizontal_wall_blocks_forward_move(): void
    {
        $state = $this->state(['walls' => [['x' => 4, 'y' => 0, 'orientation' => 'H']]]);

        $this->assertFalse($this->service->validatePawnMove($state, 'p1', 4, 1));

        // The wall spans columns 4 and 5, so a pawn at (3,0) is unaffected.
        $state['pawns']['p1'] = ['x' => 3, 'y' => 0];
        $this->assertTrue($this->service->validatePawnMove($state, 'p1', 3, 1));
    }

    public function test_vertical_wall_blocks_sideways_move(): void
    {
        $state = $this->state([
            'pawns' => ['p1' => ['x' => 4, 'y' => 4]],
            'walls' => [['x' => 4, 'y' => 4, 'orientation' => 'V']],
        ]);

        $this->assertFalse($this->service->validatePawnMove($state, 'p1', 5, 4));
        $this->assertTrue($this->service->validatePawnMove($state, 'p1', 3, 4));
    }

    public function test_straight_jump_over_adjacent_opponent(): void
    {
        $state = $this->state([
            'pawns' => ['p1' => ['x' => 4, 'y' => 4], 'p2' => ['x' => 4, 'y' => 5]],
        ]);

        $moves = $this->service->getLegalMoves($state, 'p1');

        $this->assertHasMove($moves, 4, 6);       // jump lands behind the opponent
        $this->assertNotHasMove($moves, 4, 5);    // cannot land on the opponent
        $this->assertNotHasMove($moves, 3, 5);    // no diagonal when straight jump is open
    }

    public function test_diagonal_moves_when_jump_blocked_by_wall(): void
    {
        $state = $this->state([
            'pawns' => ['p1' => ['x' => 4, 'y' => 4], 'p2' => ['x' => 4, 'y' => 5]],
            'walls' => [['x' => 4, 'y' => 5, 'orientation' => 'H']],
        ]);

        $moves = $this->service->getLegalMoves($state, 'p1');

        $this->assertNotHasMove($moves, 4, 6);
        $this->assertHasMove($moves, 3, 5);
        $this->assertHasMove($moves, 5, 5);
    }

    public function test_diagonal_moves_when_jump_blocked_by_board_edge(): void
    {
        $state = $this->state([
            'pawns' => ['p1' => ['x' => 4, 'y' => 7], 'p2' => ['x' => 4, 'y' => 8]],
        ]);

        $moves = $this->service->getLegalMoves($state, 'p1');

        $this->assertHasMove($moves, 3, 8);
        $this->assertHasMove($moves, 5, 8);
    }

    public function test_pawn_cannot_leave_board(): void
    {
        $moves = $this->service->getLegalMoves($this->state(), 'p1');

        $this->assertNotHasMove($moves, 4, -1);
        $this->assertCount(3, $moves);
    }
}
