<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Sheets
    |--------------------------------------------------------------------------
    |
    | The inventory lives in the existing Google Spreadsheet (same structure
    | as the legacy Apps Script). SHEET_ID is the spreadsheet ID from its
    | URL. GOOGLE_SERVICE_ACCOUNT_JSON carries the service account key as a
    | raw JSON string (Vercel env) or a path to the key file (local).
    |
    */

    'spreadsheet_id' => env('SHEET_ID'),

    'service_account_json' => env('GOOGLE_SERVICE_ACCOUNT_JSON'),

];
