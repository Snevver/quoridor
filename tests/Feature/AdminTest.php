<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\MatchmakingQueue;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->player = User::factory()->create();
    }

    private function makeGame(): Game
    {
        return Game::create([
            'player1_id' => $this->player->id,
            'player2_id' => User::factory()->create()->id,
            'board_state' => GameService::initialBoardState(),
            'current_turn' => 'p1',
            'status' => 'active',
            'p1_elo_before' => 1200,
            'p2_elo_before' => 1200,
        ]);
    }

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $this->actingAs($this->player)->getJson('/api/admin/stats')->assertForbidden();
        $this->actingAs($this->player)->getJson('/api/admin/users')->assertForbidden();
        $this->actingAs($this->player)->patchJson("/api/admin/users/{$this->admin->id}/ban")->assertForbidden();
    }

    public function test_admin_can_view_stats(): void
    {
        $this->makeGame();

        $this->actingAs($this->admin)
            ->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJson(['users' => 3, 'games_total' => 1, 'games_active' => 1]);
    }

    public function test_admin_can_adjust_elo(): void
    {
        $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$this->player->id}/elo", ['elo' => 1500])
            ->assertOk();

        $this->assertSame(1500, $this->player->fresh()->elo);
    }

    public function test_admin_can_ban_and_banned_user_is_locked_out(): void
    {
        MatchmakingQueue::create(['user_id' => $this->player->id, 'elo_at_join' => 1200]);

        $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$this->player->id}/ban")
            ->assertOk();

        $this->assertNotNull($this->player->fresh()->banned_at);
        $this->assertDatabaseMissing('matchmaking_queue', ['user_id' => $this->player->id]);

        // Banned users are rejected on authenticated routes.
        $this->actingAs($this->player->fresh())->getJson('/api/user/me')->assertForbidden();

        // Unban restores access.
        $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$this->player->id}/ban")
            ->assertOk();
        $this->assertNull($this->player->fresh()->banned_at);
    }

    public function test_banned_user_cannot_login(): void
    {
        $this->player->update(['banned_at' => now()]);

        $this->postJson('/api/login', ['email' => $this->player->email, 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_admin_cannot_ban_or_demote_self(): void
    {
        $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$this->admin->id}/ban")
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$this->admin->id}/admin")
            ->assertStatus(422);
    }

    public function test_admin_can_force_end_game_with_winner(): void
    {
        $game = $this->makeGame();

        $this->assertNotNull($game->slug);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/games/{$game->slug}/end", ['result' => 'p2'])
            ->assertOk();

        $game->refresh();
        $this->assertSame('finished', $game->status);
        $this->assertSame($game->player2_id, $game->winner_id);
        $this->assertSame(1216, $game->player2->fresh()->elo);
        $this->assertSame(1184, $game->player1->fresh()->elo);
    }

    public function test_admin_can_void_game_without_elo_changes(): void
    {
        $game = $this->makeGame();
        $eloBefore = $this->player->fresh()->elo;

        $this->actingAs($this->admin)
            ->postJson("/api/admin/games/{$game->slug}/end", ['result' => 'void'])
            ->assertOk();

        $game->refresh();
        $this->assertSame('finished', $game->status);
        $this->assertNull($game->winner_id);
        $this->assertNull($game->board_state['winner']);
        $this->assertSame($eloBefore, $this->player->fresh()->elo);
        $this->assertSame($game->p1_elo_before, $game->p1_elo_after);
    }

    public function test_admin_can_manage_queue(): void
    {
        MatchmakingQueue::create(['user_id' => $this->player->id, 'elo_at_join' => 1200]);

        $this->actingAs($this->admin)->getJson('/api/admin/queue')
            ->assertOk()
            ->assertJsonCount(1, 'entries');

        $this->actingAs($this->admin)->deleteJson('/api/admin/queue')->assertOk();
        $this->assertSame(0, MatchmakingQueue::count());
    }

    public function test_admin_plays_like_a_normal_player(): void
    {
        // Admins appear in matchmaking and the leaderboard like anyone else.
        $this->actingAs($this->admin)->postJson('/api/matchmaking/join')->assertOk();
        $this->assertDatabaseHas('matchmaking_queue', ['user_id' => $this->admin->id]);

        $this->actingAs($this->admin)->getJson('/api/leaderboard')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->admin->id]);
    }
}
