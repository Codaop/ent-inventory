<?php

namespace App\Providers;

use App\Sheets\GoogleSheetClient;
use App\Sheets\SheetClient;
use Google\Client as GoogleClient;
use Google\Service\Sheets;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SheetClient::class, function ($app) {
            $json = (string) $app['config']->get('sheets.service_account_json', '');

            $google = new GoogleClient;
            $google->setScopes([Sheets::SPREADSHEETS]);

            if ($json !== '') {
                $decoded = json_decode($json, true);
                $google->setAuthConfig(is_array($decoded) ? $decoded : $json);
            }

            return new GoogleSheetClient(
                $google,
                (string) $app['config']->get('sheets.spreadsheet_id', ''),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
