<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateUser;
use App\Repositories\UserSheetRepository;
use App\Sheets\SheetClient;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\Support\InMemorySheetClient;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    private InMemorySheetClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new InMemorySheetClient([
            'Users' => [['email', 'displayName', 'role', 'active']],
        ]);
        $this->app->instance(SheetClient::class, $this->client);
    }

    private function fakeGoogleUser(string $email): void
    {
        $googleUser = new SocialiteUser;
        $googleUser->id = 'google-123';
        $googleUser->name = 'Budi';
        $googleUser->email = $email;

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($googleUser);
        $provider->shouldReceive('redirect')
            ->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_login_page_renders_a_google_sign_in_link(): void
    {
        $this->get('/login')
            ->assertStatus(200)
            ->assertSee('/auth/google');
    }

    public function test_google_redirect_sends_the_user_to_google(): void
    {
        $this->fakeGoogleUser('budi@example.com');

        $this->get('/auth/google')->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_callback_logs_in_an_active_sheet_user(): void
    {
        (new UserSheetRepository($this->client))->appendUser([
            'email' => 'budi@example.com',
            'displayName' => 'Budi',
        ]);
        $this->fakeGoogleUser('budi@example.com');

        $this->get('/auth/google/callback')
            ->assertRedirect('/')
            ->assertSessionHas(AuthenticateUser::SESSION_KEY, 'budi@example.com');
    }

    public function test_callback_rejects_a_google_user_that_is_not_an_active_sheet_user(): void
    {
        $this->fakeGoogleUser('ghost@example.com');

        $this->get('/auth/google/callback')
            ->assertRedirect('/login?error=ACCESS_DENIED')
            ->assertSessionMissing(AuthenticateUser::SESSION_KEY);

        $repository = new UserSheetRepository($this->client);
        $repository->appendUser(['email' => 'budi@example.com']);
        $repository->updateUserByEmail('budi@example.com', ['active' => false]);

        $this->fakeGoogleUser('budi@example.com');

        $this->get('/auth/google/callback')
            ->assertRedirect('/login?error=ACCESS_DENIED')
            ->assertSessionMissing(AuthenticateUser::SESSION_KEY);
    }

    public function test_logout_clears_the_login_session(): void
    {
        $this->withSession([AuthenticateUser::SESSION_KEY => 'budi@example.com'])
            ->post('/auth/logout')
            ->assertRedirect('/login')
            ->assertSessionMissing(AuthenticateUser::SESSION_KEY);
    }
}
