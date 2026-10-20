<?php

namespace Tests\Unit;

use App\Repositories\CategorySheetRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemorySheetClient;

class CategorySheetRepositoryTest extends TestCase
{
    public function test_lists_categories_and_ensures_new_ones_case_insensitively(): void
    {
        $client = new InMemorySheetClient([
            'Categories' => [
                ['name'],
                ['Network'],
                ['Cable'],
                [''],
            ],
        ]);
        $repository = new CategorySheetRepository($client);

        $this->assertSame(['Network', 'Cable'], $repository->all(), 'empty names are filtered out');

        $repository->ensureExists('network');
        $this->assertSame(['Network', 'Cable'], $repository->all(), 'existing category (any case) is not duplicated');

        $repository->ensureExists('Storage');
        $this->assertSame(['Network', 'Cable', 'Storage'], $repository->all());

        $repository->ensureExists('');
        $this->assertCount(3, $repository->all(), 'empty category names are ignored');
    }
}
