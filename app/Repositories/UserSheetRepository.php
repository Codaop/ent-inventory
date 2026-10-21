<?php

namespace App\Repositories;

use App\Sheets\SheetClient;

class UserSheetRepository
{
    public function __construct(private readonly SheetClient $client) {}

    /**
     * Find an active user by email (case-insensitive, trimmed), mirroring
     * the original getCurrentUser() logic in Auth.gs.
     *
     * @return array{email: string, displayName: string, role: string, active: bool}|null
     */
    public function findActiveUserByEmail(string $email): ?array
    {
        $needle = strtolower(trim($email));

        foreach ($this->client->readRows('Users') as $row) {
            if (! isset($row[0]) || $row[0] === '') {
                continue;
            }

            $isActive = $this->toBool($row[3] ?? false);
            $rowEmail = strtolower(trim((string) $row[0]));

            if ($rowEmail === $needle && $isActive) {
                return [
                    'email' => (string) $row[0],
                    'displayName' => $row[1] ? (string) $row[1] : (string) $row[0],
                    'role' => (string) $row[2],
                    'active' => true,
                ];
            }
        }

        return null;
    }

    /**
     * List all users with active flags, mirroring getUsers() in Auth.gs.
     *
     * @return list<array{email: string, displayName: string, role: string, active: bool}>
     */
    public function allUsers(): array
    {
        $users = [];

        foreach ($this->client->readRows('Users') as $row) {
            if (! isset($row[0]) || $row[0] === '') {
                continue;
            }

            $users[] = [
                'email' => (string) $row[0],
                'displayName' => (string) ($row[1] ?? ''),
                'role' => (string) $row[2],
                'active' => $this->toBool($row[3] ?? false),
            ];
        }

        return $users;
    }

    /**
     * Case-insensitive email lookup regardless of active state.
     */
    public function existsByEmail(string $email): bool
    {
        $needle = strtolower(trim($email));

        foreach ($this->client->readRows('Users') as $row) {
            if (isset($row[0]) && strtolower(trim((string) $row[0])) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Append a new user row with the defaults from createUser() in Auth.gs:
     * displayName falls back to email, role defaults to BORROWER, active is true.
     *
     * @param  array{email: string, displayName?: string|null, role?: string|null}  $payload
     */
    public function appendUser(array $payload): void
    {
        $displayName = $payload['displayName'] ?? null;
        $role = $payload['role'] ?? null;

        $this->client->appendRow('Users', [
            $payload['email'],
            $displayName ?: $payload['email'],
            $role ?: 'BORROWER',
            true,
        ]);
    }

    /**
     * Partially update a user row, mirroring updateUser() in Auth.gs.
     * Unknown fields on the payload are ignored; a missing user returns false.
     *
     * @param  array{displayName?: string, role?: string, active?: bool}  $changes
     */
    public function updateUserByEmail(string $email, array $changes): bool
    {
        $needle = strtolower(trim($email));

        foreach ($this->client->readRows('Users') as $index => $row) {
            if (! isset($row[0]) || strtolower(trim((string) $row[0])) !== $needle) {
                continue;
            }

            $spreadsheetRow = $index + 2; // header row + 1-based
            $cells = [];

            if (array_key_exists('displayName', $changes)) {
                $cells[2] = $changes['displayName'];
            }
            if (array_key_exists('role', $changes)) {
                $cells[3] = $changes['role'];
            }
            if (array_key_exists('active', $changes)) {
                $cells[4] = $changes['active'];
            }

            if ($cells !== []) {
                $this->client->updateCells('Users', $spreadsheetRow, $cells);
            }

            return true;
        }

        return false;
    }

    private function toBool(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        return strtoupper(trim((string) $value)) === 'TRUE';
    }
}
