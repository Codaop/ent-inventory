<?php

namespace App\Repositories;

use App\Sheets\SheetClient;

class ConfigSheetRepository
{
    public function __construct(private readonly SheetClient $client) {}

    /**
     * Get all config as key-value pairs.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $config = [];

        foreach ($this->client->readRows('Config') as $row) {
            if (isset($row[0]) && $row[0] !== '') {
                $config[(string) $row[0]] = (string) ($row[1] ?? '');
            }
        }

        return $config;
    }

    /**
     * Get a single config value by key.
     */
    public function get(string $key): ?string
    {
        foreach ($this->client->readRows('Config') as $row) {
            if (isset($row[0]) && (string) $row[0] === $key) {
                return (string) ($row[1] ?? '');
            }
        }

        return null;
    }

    /**
     * Set a config key (upsert).
     */
    public function set(string $key, string $value): void
    {
        foreach ($this->client->readRows('Config') as $index => $row) {
            if (isset($row[0]) && (string) $row[0] === $key) {
                $this->client->updateCells('Config', $index + 2, [2 => $value]);

                return;
            }
        }

        $this->client->appendRow('Config', [$key, $value]);
    }

    /**
     * Delete a config key. Returns true if deleted, false if not found.
     */
    public function delete(string $key): bool
    {
        foreach ($this->client->readRows('Config') as $index => $row) {
            if (isset($row[0]) && (string) $row[0] === $key) {
                // Clear the key cell to "delete" - Config sheet uses key|value
                $this->client->updateCells('Config', $index + 2, [1 => '', 2 => '']);

                return true;
            }
        }

        return false;
    }
}
