<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $id, string $email): void
    {
        $googleUser = (new SocialiteUser())->setRaw([])->map([
            'id' => $id,
            'email' => $email,
        ]);

        $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_new_google_user_is_sent_to_choose_a_name(): void
    {
        $this->fakeGoogleUser('g-123', 'new@player.gg');

        $this->get('/auth/google/callback')->assertRedirect('/choose-name');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'new@player.gg']);

        $this->getJson('/auth/google/pending')->assertOk()->assertJson(['pending' => true]);

        $response = $this->postJson('/auth/google/complete', ['name' => 'WallLord']);
        $response->assertCreated()->assertJsonPath('name', 'WallLord');

        $this->assertAuthenticated('web');
        $user = User::where('email', 'new@player.gg')->first();
        $this->assertSame('g-123', $user->google_id);
        $this->assertNull($user->password);
        $this->assertSame(1200, $user->elo);
    }

    public function test_existing_email_account_is_linked_and_logged_in(): void
    {
        $user = User::factory()->create(['email' => 'old@player.gg']);
        $this->fakeGoogleUser('g-456', 'old@player.gg');

        $this->get('/auth/google/callback')->assertRedirect('/lobby');

        $this->assertAuthenticatedAs($user->fresh(), 'web');
        $this->assertSame('g-456', $user->fresh()->google_id);
    }

    public function test_returning_google_user_logs_in_by_google_id(): void
    {
        $user = User::factory()->create(['email' => 'back@player.gg', 'google_id' => 'g-789']);
        // Google id wins even if the email on the Google account changed.
        $this->fakeGoogleUser('g-789', 'changed@player.gg');

        $this->get('/auth/google/callback')->assertRedirect('/lobby');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_banned_users_cannot_enter_via_google(): void
    {
        User::factory()->create(['email' => 'bad@player.gg', 'banned_at' => now()]);
        $this->fakeGoogleUser('g-666', 'bad@player.gg');

        $this->get('/auth/google/callback')->assertRedirect('/login?error=banned');
        $this->assertGuest();
    }

    public function test_complete_requires_a_pending_google_signin(): void
    {
        $this->postJson('/auth/google/complete', ['name' => 'Ghost'])->assertNotFound();
    }

    public function test_complete_rejects_a_taken_battle_name(): void
    {
        User::factory()->create(['name' => 'Taken']);
        $this->fakeGoogleUser('g-321', 'fresh@player.gg');
        $this->get('/auth/google/callback');

        $this->postJson('/auth/google/complete', ['name' => 'Taken'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertGuest();
    }
}
