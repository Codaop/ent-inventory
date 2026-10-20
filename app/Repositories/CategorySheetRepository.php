<?php

namespace App\Repositories;

use App\Sheets\SheetClient;

class CategorySheetRepository
{
    public function __construct(private readonly SheetClient $client) {}

    /**
     * All category names, mirroring getCategories() in Inventory.gs.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $names = [];

        foreach ($this->client->readRows('Categories') as $row) {
            $name = trim((string) ($row[0] ?? ''));

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Append the category when no existing row matches it case-insensitively,
     * mirroring ensureCategory_() in Inventory.gs.
     */
    public function ensureExists(string $name): void
    {
        if ($name === '') {
            return;
        }

        $needle = strtolower($name);

        foreach ($this->client->readRows('Categories') as $row) {
            if (strtolower(trim((string) ($row[0] ?? ''))) === $needle) {
                return;
            }
        }

        $this->client->appendRow('Categories', [$name]);
    }
}
