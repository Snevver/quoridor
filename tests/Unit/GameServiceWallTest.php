<?php

namespace Tests\Unit;

use App\Services\EloService;
use App\Services\GameService;
use PHPUnit\Framework\TestCase;

class GameServiceWallTest extends TestCase
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

    public function test_valid_wall_placement(): void
    {
        $this->assertTrue($this->service->validateWallPlacement($this->state(), 'p1', 4, 4, 'H'));
        $this->assertTrue($this->service->validateWallPlacement($this->state(), 'p2', 0, 0, 'V'));
    }

    public function test_wall_out_of_bounds_is_rejected(): void
    {
        $this->assertFalse($this->service->validateWallPlacement($this->state(), 'p1', 8, 4, 'H'));
        $this->assertFalse($this->service->validateWallPlacement($this->state(), 'p1', 4, -1, 'V'));
    }

    public function test_wall_requires_remaining_walls(): void
    {
        $state = $this->state(['walls_left' => ['p1' => 0]]);

        $this->assertFalse($this->service->validateWallPlacement($state, 'p1', 4, 4, 'H'));
        $this->assertTrue($this->service->validateWallPlacement($state, 'p2', 4, 4, 'H'));
    }

    public function test_overlapping_walls_are_rejected(): void
    {
        $state = $this->state(['walls' => [['x' => 4, 'y' => 4, 'orientation' => 'H']]]);

        // Exact duplicate and perpendicular cross at the same groove point.
        $this->assertFalse($this->service->validateWallPlacement($state, 'p1', 4, 4, 'H'));
        $this->assertFalse($this->service->validateWallPlacement($state, 'p1', 4, 4, 'V'));

        // Sliding overlap along the same row.
        $this->assertFalse($this->service->validateWallPlacement($state, 'p1', 3, 4, 'H'));
        $this->assertFalse($this->service->validateWallPlacement($state, 'p1', 5, 4, 'H'));

        // Two grooves over is fine, as is a parallel wall one row down.
        $this->assertTrue($this->service->validateWallPlacement($state, 'p1', 6, 4, 'H'));
        $this->assertTrue($this->service->validateWallPlacement($state, 'p1', 4, 5, 'H'));
    }

    public function test_overlapping_vertical_walls_are_rejected(): void
    {
        $state = $this->state(['walls' => [['x' => 4, 'y' => 4, 'orientation' => 'V']]]);

        $this->assertFalse($this->service->validateWallPlacement($state, 'p1', 4, 3, 'V'));
        $this->assertFalse($this->service->validateWallPlacement($state, 'p1', 4, 5, 'V'));
        $this->assertTrue($this->service->validateWallPlacement($state, 'p1', 4, 6, 'V'));
    }

    public function test_wall_that_seals_off_a_player_is_rejected(): void
    {
        // Four horizontal walls cover columns 0-7 below row 0; the final
        // vertical wall would seal the only remaining gap at column 8.
        $state = $this->state([
            'walls' => [
                ['x' => 0, 'y' => 0, 'orientation' => 'H'],
                ['x' => 2, 'y' => 0, 'orientation' => 'H'],
                ['x' => 4, 'y' => 0, 'orientation' => 'H'],
                ['x' => 6, 'y' => 0, 'orientation' => 'H'],
            ],
        ]);

        $this->assertFalse($this->service->validateWallPlacement($state, 'p2', 7, 0, 'V'));

        // Any other legal wall is still fine.
        $this->assertTrue($this->service->validateWallPlacement($state, 'p2', 4, 4, 'H'));
    }

    public function test_bfs_finds_path_on_open_board(): void
    {
        $this->assertTrue($this->service->bfsHasPath($this->state(), 'p1'));
        $this->assertTrue($this->service->bfsHasPath($this->state(), 'p2'));
    }

    public function test_bfs_detects_sealed_player(): void
    {
        $state = $this->state([
            'walls' => [
                ['x' => 0, 'y' => 0, 'orientation' => 'H'],
                ['x' => 2, 'y' => 0, 'orientation' => 'H'],
                ['x' => 4, 'y' => 0, 'orientation' => 'H'],
                ['x' => 6, 'y' => 0, 'orientation' => 'H'],
                ['x' => 7, 'y' => 0, 'orientation' => 'V'],
            ],
        ]);

        $this->assertFalse($this->service->bfsHasPath($state, 'p1'));
        $this->assertTrue($this->service->bfsHasPath($state, 'p2'));
    }
}
