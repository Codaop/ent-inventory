<?php

namespace Tests\Feature;

use App\Repositories\UserSheetRepository;
use App\Sheets\SheetClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\Support\InMemorySheetClient;
use Tests\TestCase;

class AuthMiddlewareTest extends TestCase
{
    private InMemorySheetClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new InMemorySheetClient([
            'Users' => [['email', 'displayName', 'role', 'active']],
        ]);
        $this->app->instance(SheetClient::class, $this->client);

        Route::middleware(['web', 'auth'])->get('/__protected', function (Request $request) {
            $user = $request->attributes->get('currentUser');

            return response()->json(['email' => $user['email'], 'role' => $user['role']]);
        });
    }

    public function test_auth_blocks_json_requests_without_a_login_session(): void
    {
        $this->getJson('/__protected')
            ->assertStatus(401)
            ->assertJson(['error' => 'ACCESS_DENIED']);
    }

    public function test_auth_redirects_web_requests_without_a_login_session_to_login(): void
    {
        $this->get('/__protected')->assertRedirect('/login');
    }

    public function test_auth_allows_an_active_sheet_user_stored_in_the_session(): void
    {
        (new UserSheetRepository($this->client))->appendUser([
            'email' => 'Budi@Example.com',
            'displayName' => 'Budi',
            'role' => 'BORROWER',
        ]);

        $this->withSession(['auth_email' => 'budi@example.com'])
            ->getJson('/__protected')
            ->assertStatus(200)
            ->assertJson(['email' => 'Budi@Example.com', 'role' => 'BORROWER']);
    }

    public function test_auth_rejects_a_session_whose_sheet_user_is_missing_or_inactive(): void
    {
        $this->withSession(['auth_email' => 'ghost@example.com'])
            ->getJson('/__protected')
            ->assertStatus(401)
            ->assertJson(['error' => 'ACCESS_DENIED']);

        $repository = new UserSheetRepository($this->client);
        $repository->appendUser(['email' => 'budi@example.com']);
        $repository->updateUserByEmail('budi@example.com', ['active' => false]);

        $this->withSession(['auth_email' => 'budi@example.com'])
            ->getJson('/__protected')
            ->assertStatus(401)
            ->assertJson(['error' => 'ACCESS_DENIED']);
    }

    public function test_admin_middleware_requires_the_admin_role(): void
    {
        $repository = new UserSheetRepository($this->client);
        $repository->appendUser(['email' => 'borrower@example.com', 'role' => 'BORROWER']);
        $repository->appendUser(['email' => 'boss@example.com', 'role' => 'ADMIN']);

        Route::middleware(['web', 'auth', 'admin'])->get('/__admin', fn () => response()->json(['ok' => true]));

        $this->withSession(['auth_email' => 'borrower@example.com'])
            ->getJson('/__admin')
            ->assertStatus(403)
            ->assertJson(['error' => 'ADMIN_REQUIRED']);

        $this->withSession(['auth_email' => 'borrower@example.com'])
            ->get('/__admin')
            ->assertStatus(403);

        $this->withSession(['auth_email' => 'boss@example.com'])
            ->getJson('/__admin')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }
}
