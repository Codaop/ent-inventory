<?php

namespace Tests\Feature;

use App\Sheets\GoogleSheetClient;
use App\Sheets\SheetClient;
use Tests\TestCase;

class SheetClientBindingTest extends TestCase
{
    public function test_sheet_client_resolves_to_the_google_adapter(): void
    {
        $client = $this->app->make(SheetClient::class);

        $this->assertInstanceOf(GoogleSheetClient::class, $client);
        $this->assertSame($client, $this->app->make(SheetClient::class), 'the binding is a singleton');
    }
}
