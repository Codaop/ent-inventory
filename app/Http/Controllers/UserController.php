<?php

namespace App\Http\Controllers;

use App\Repositories\UserSheetRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserSheetRepository $users) {}

    /**
     * List all users (active and inactive). Admin only via route middleware.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->users->allUsers());
    }

    /**
     * Create a user, mirroring createUser() in Auth.gs: case-insensitive
     * EMAIL_EXISTS check, then append row with defaults.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'displayName' => ['nullable', 'string'],
            'role' => ['nullable', 'string', 'in:ADMIN,BORROWER'],
        ]);

        if ($this->users->existsByEmail($data['email'])) {
            return $this->error('EMAIL_EXISTS', 422);
        }

        $this->users->appendUser([
            'email' => $data['email'],
            'displayName' => $data['displayName'] ?? null,
            'role' => $data['role'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Update a user (partial), mirroring updateUser() in Auth.gs: unknown
     * fields ignored, missing user returns USER_NOT_FOUND.
     */
    public function update(Request $request, string $email): JsonResponse
    {
        $data = $request->validate([
            'displayName' => ['sometimes', 'nullable', 'string'],
            'role' => ['sometimes', 'nullable', 'string', 'in:ADMIN,BORROWER'],
            'active' => ['sometimes', 'boolean'],
        ]);

        if (! $this->users->updateUserByEmail($email, $data)) {
            return $this->error('USER_NOT_FOUND', 404);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Delete a user by setting active=false (soft delete), mirroring
     * deleteUser() in Auth.gs.
     */
    public function destroy(string $email): JsonResponse
    {
        if (! $this->users->updateUserByEmail($email, ['active' => false])) {
            return $this->error('USER_NOT_FOUND', 404);
        }

        return response()->json(['ok' => true]);
    }

    protected function error(string $code, int $status): JsonResponse
    {
        return response()->json(['error' => $code], $status);
    }
}
