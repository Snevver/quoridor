<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_shows_stats_and_recent_games(): void
    {
        $alice = User::factory()->create(['elo' => 1216, 'games_played' => 1, 'games_won' => 1]);
        $bob = User::factory()->create(['elo' => 1184, 'games_played' => 1]);

        $state = GameService::initialBoardState();
        $state['status'] = 'finished';
        $state['winner'] = 'p1';

        Game::create([
            'player1_id' => $alice->id,
            'player2_id' => $bob->id,
            'board_state' => $state,
            'status' => 'finished',
            'winner_id' => $alice->id,
            'p1_elo_before' => 1200,
            'p2_elo_before' => 1200,
            'p1_elo_after' => 1216,
            'p2_elo_after' => 1184,
        ]);

        $response = $this->actingAs($bob)
            ->getJson("/api/users/{$alice->id}")
            ->assertOk()
            ->json();

        $this->assertSame($alice->name, $response['user']['name']);
        $this->assertSame(1216, $response['user']['elo']);
        $this->assertArrayNotHasKey('email', $response['user']);

        $this->assertCount(1, $response['recent_games']);
        $this->assertTrue($response['recent_games'][0]['won']);
        $this->assertSame(16, $response['recent_games'][0]['elo_change']);
        $this->assertSame($bob->name, $response['recent_games'][0]['opponent']['name']);

        // Bob's own profile shows the loss from his perspective.
        $bobView = $this->actingAs($alice)->getJson("/api/users/{$bob->id}")->json();
        $this->assertFalse($bobView['recent_games'][0]['won']);
        $this->assertSame(-16, $bobView['recent_games'][0]['elo_change']);
    }

    public function test_profile_requires_authentication(): void
    {
        $user = User::factory()->create();

        $this->getJson("/api/users/{$user->id}")->assertUnauthorized();
    }
}
