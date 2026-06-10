<?php

namespace Tests\Feature;

use App\Models\Rank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_ranks_are_seeded_by_migration(): void
    {
        $this->assertSame(6, Rank::count());
        $this->assertSame('Bronze', Rank::forElo(0)->name);
        $this->assertSame('Bronze', Rank::forElo(1149)->name);
        $this->assertSame('Silver', Rank::forElo(1150)->name);
        $this->assertSame('Gold', Rank::forElo(1399)->name);
        $this->assertSame('Grandmaster', Rank::forElo(2400)->name);
    }

    public function test_players_can_list_ranks(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/ranks')
            ->assertOk()
            ->assertJsonCount(6);
    }

    public function test_admin_can_manage_ranks(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $created = $this->actingAs($admin)
            ->postJson('/api/admin/ranks', ['name' => 'Legend', 'min_elo' => 2200, 'color' => '#ff00ff'])
            ->assertCreated()
            ->json();

        $this->actingAs($admin)
            ->patchJson("/api/admin/ranks/{$created['id']}", ['name' => 'Mythic', 'min_elo' => 2300, 'color' => '#ff00aa'])
            ->assertOk();

        $this->assertSame('Mythic', Rank::forElo(2300)->name);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/ranks/{$created['id']}")
            ->assertOk();

        $this->assertSame(6, Rank::count());
    }

    public function test_non_admin_cannot_manage_ranks(): void
    {
        $rank = Rank::first();

        $this->actingAs(User::factory()->create())
            ->patchJson("/api/admin/ranks/{$rank->id}", ['name' => 'X', 'min_elo' => 1, 'color' => '#000000'])
            ->assertForbidden();
    }

    public function test_duplicate_thresholds_are_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson('/api/admin/ranks', ['name' => 'Copy', 'min_elo' => 1300, 'color' => '#123456'])
            ->assertUnprocessable();
    }
}
