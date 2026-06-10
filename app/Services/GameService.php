<?php

namespace App\Services;

use App\Exceptions\InvalidMoveException;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Server-authoritative Quoridor rules engine.
 *
 * Coordinates: x = column (0..8), y = row (0..8).
 * P1 starts at (4,0) and must reach row 8; P2 starts at (4,8) and must reach row 0.
 *
 * Walls live in grooves: a wall {x, y, orientation} with x,y in 0..7.
 *  - "H" blocks movement between row y and y+1, spanning columns x and x+1.
 *  - "V" blocks movement between column x and x+1, spanning rows y and y+1.
 */
class GameService
{
    public const SIZE = 9;

    private const DIRECTIONS = [[1, 0], [-1, 0], [0, 1], [0, -1]];

    public function __construct(private EloService $elo)
    {
    }

    public static function initialBoardState(): array
    {
        return [
            'pawns' => [
                'p1' => ['x' => 4, 'y' => 0],
                'p2' => ['x' => 4, 'y' => 8],
            ],
            'walls' => [],
            'walls_left' => ['p1' => 10, 'p2' => 10],
            'current_turn' => 'p1',
            'status' => 'active',
            'winner' => null,
        ];
    }

    public static function goalRow(string $player): int
    {
        return $player === 'p1' ? 8 : 0;
    }

    public function validatePawnMove(array $boardState, string $player, int $toX, int $toY): bool
    {
        foreach ($this->getLegalMoves($boardState, $player) as $move) {
            if ($move['x'] === $toX && $move['y'] === $toY) {
                return true;
            }
        }

        return false;
    }

    /**
     * All legal pawn destinations for $player, including jumps and diagonals.
     *
     * @return array<int, array{x: int, y: int}>
     */
    public function getLegalMoves(array $boardState, string $player): array
    {
        $me = $boardState['pawns'][$player];
        $opponent = $boardState['pawns'][$player === 'p1' ? 'p2' : 'p1'];
        $walls = $boardState['walls'];
        $moves = [];

        foreach (self::DIRECTIONS as [$dx, $dy]) {
            $nx = $me['x'] + $dx;
            $ny = $me['y'] + $dy;

            if (!$this->inBounds($nx, $ny) || $this->isBlocked($walls, $me['x'], $me['y'], $nx, $ny)) {
                continue;
            }

            if ($opponent['x'] !== $nx || $opponent['y'] !== $ny) {
                $moves[] = ['x' => $nx, 'y' => $ny];
                continue;
            }

            // Opponent is adjacent: try jumping straight over.
            $jx = $nx + $dx;
            $jy = $ny + $dy;
            if ($this->inBounds($jx, $jy) && !$this->isBlocked($walls, $nx, $ny, $jx, $jy)) {
                $moves[] = ['x' => $jx, 'y' => $jy];
                continue;
            }

            // Straight jump blocked by wall or edge: diagonal steps off the opponent.
            foreach (self::DIRECTIONS as [$px, $py]) {
                $sx = $nx + $px;
                $sy = $ny + $py;
                if ($sx === $me['x'] && $sy === $me['y']) {
                    continue;
                }
                if ($sx === $jx && $sy === $jy) {
                    continue;
                }
                if ($this->inBounds($sx, $sy) && !$this->isBlocked($walls, $nx, $ny, $sx, $sy)) {
                    $moves[] = ['x' => $sx, 'y' => $sy];
                }
            }
        }

        return $moves;
    }

    public function validateWallPlacement(array $boardState, string $player, int $x, int $y, string $orientation): bool
    {
        if ($x < 0 || $x > 7 || $y < 0 || $y > 7 || !in_array($orientation, ['H', 'V'], true)) {
            return false;
        }

        if ($boardState['walls_left'][$player] < 1) {
            return false;
        }

        foreach ($boardState['walls'] as $wall) {
            if ($this->wallsConflict($wall, ['x' => $x, 'y' => $y, 'orientation' => $orientation])) {
                return false;
            }
        }

        // Neither player may be fully walled off from their goal.
        $boardState['walls'][] = ['x' => $x, 'y' => $y, 'orientation' => $orientation];

        return $this->bfsHasPath($boardState, 'p1') && $this->bfsHasPath($boardState, 'p2');
    }

    public function bfsHasPath(array $boardState, string $player): bool
    {
        $start = $boardState['pawns'][$player];
        $goalY = self::goalRow($player);
        $walls = $boardState['walls'];

        $visited = array_fill(0, self::SIZE * self::SIZE, false);
        $visited[$start['y'] * self::SIZE + $start['x']] = true;
        $queue = [[$start['x'], $start['y']]];

        while ($queue !== []) {
            [$x, $y] = array_shift($queue);

            if ($y === $goalY) {
                return true;
            }

            foreach (self::DIRECTIONS as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;
                $key = $ny * self::SIZE + $nx;

                if (!$this->inBounds($nx, $ny) || $visited[$key] || $this->isBlocked($walls, $x, $y, $nx, $ny)) {
                    continue;
                }

                $visited[$key] = true;
                $queue[] = [$nx, $ny];
            }
        }

        return false;
    }

    /**
     * Validate and apply a move for $user, persist everything, and return the new board state.
     *
     * @param array{move_type: string, to?: array{int, int}, x?: int, y?: int, orientation?: string} $payload
     *
     * @throws InvalidMoveException
     */
    public function applyMove(Game $game, User $user, array $payload): array
    {
        return DB::transaction(function () use ($game, $user, $payload) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();

            $player = $game->roleOf($user);
            $state = $game->board_state;

            if ($game->status !== 'active') {
                throw new InvalidMoveException('This game is already over.');
            }
            if ($state['current_turn'] !== $player) {
                throw new InvalidMoveException('It is not your turn.');
            }

            if ($payload['move_type'] === 'pawn') {
                [$toX, $toY] = [(int) $payload['to'][0], (int) $payload['to'][1]];

                if (!$this->validatePawnMove($state, $player, $toX, $toY)) {
                    throw new InvalidMoveException('Illegal pawn move.');
                }

                $state['pawns'][$player] = ['x' => $toX, 'y' => $toY];
                $movePayload = ['to' => [$toX, $toY]];
            } else {
                [$x, $y, $orientation] = [(int) $payload['x'], (int) $payload['y'], $payload['orientation']];

                if (!$this->validateWallPlacement($state, $player, $x, $y, $orientation)) {
                    throw new InvalidMoveException('Illegal wall placement.');
                }

                $state['walls'][] = ['x' => $x, 'y' => $y, 'orientation' => $orientation];
                $state['walls_left'][$player]--;
                $movePayload = ['x' => $x, 'y' => $y, 'orientation' => $orientation];
            }

            $won = $payload['move_type'] === 'pawn'
                && $state['pawns'][$player]['y'] === self::goalRow($player);

            if ($won) {
                $state['status'] = 'finished';
                $state['winner'] = $player;
            } else {
                $state['current_turn'] = $player === 'p1' ? 'p2' : 'p1';
            }

            $game->moves()->create([
                'player_id' => $user->id,
                'move_type' => $payload['move_type'],
                'payload' => $movePayload,
                'move_number' => $game->moves()->count() + 1,
            ]);

            $game->board_state = $state;
            $game->current_turn = $state['current_turn'];

            if ($won) {
                $this->finishGame($game, $player);
            } else {
                $game->save();
            }

            return $state;
        });
    }

    /**
     * Mark the game finished, settle ELO and player stats. Used for wins and resignations.
     */
    public function finishGame(Game $game, string $winnerRole): void
    {
        $winner = $winnerRole === 'p1' ? $game->player1 : $game->player2;
        $loser = $winnerRole === 'p1' ? $game->player2 : $game->player1;

        $state = $game->board_state;
        $state['status'] = 'finished';
        $state['winner'] = $winnerRole;

        $ratings = $this->elo->calculate(
            $game->player1->elo,
            $game->player2->elo,
            $winnerRole === 'p1' ? 'a_wins' : 'b_wins'
        );

        $game->board_state = $state;
        $game->status = 'finished';
        $game->winner_id = $winner->id;
        $game->p1_elo_after = $ratings['a'];
        $game->p2_elo_after = $ratings['b'];
        $game->save();

        $game->player1->update(['elo' => $ratings['a'], 'games_played' => $game->player1->games_played + 1]);
        $game->player2->update(['elo' => $ratings['b'], 'games_played' => $game->player2->games_played + 1]);
        $winner->increment('games_won');
    }

    private function inBounds(int $x, int $y): bool
    {
        return $x >= 0 && $x < self::SIZE && $y >= 0 && $y < self::SIZE;
    }

    /**
     * Is the single orthogonal step from (fx,fy) to (tx,ty) blocked by a wall?
     */
    private function isBlocked(array $walls, int $fx, int $fy, int $tx, int $ty): bool
    {
        foreach ($walls as $wall) {
            if ($wall['orientation'] === 'H') {
                if ($fx !== $tx) {
                    continue;
                }
                if ($wall['y'] === min($fy, $ty) && ($wall['x'] === $fx || $wall['x'] === $fx - 1)) {
                    return true;
                }
            } else {
                if ($fy !== $ty) {
                    continue;
                }
                if ($wall['x'] === min($fx, $tx) && ($wall['y'] === $fy || $wall['y'] === $fy - 1)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Two walls conflict when they overlap along their span or cross at the same groove point.
     */
    private function wallsConflict(array $a, array $b): bool
    {
        if ($a['x'] === $b['x'] && $a['y'] === $b['y']) {
            return true;
        }

        if ($a['orientation'] !== $b['orientation']) {
            return false;
        }

        if ($a['orientation'] === 'H') {
            return $a['y'] === $b['y'] && abs($a['x'] - $b['x']) === 1;
        }

        return $a['x'] === $b['x'] && abs($a['y'] - $b['y']) === 1;
    }
}
