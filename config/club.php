<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Club Name & Organization Details
    |--------------------------------------------------------------------------
    */
    'name' => env('CLUB_NAME', 'Malabar Club'),
    'short_name' => env('CLUB_SHORT_NAME', 'MC'),
    'location' => env('CLUB_LOCATION', 'Vadakkencherry, Kerala'),
    'address' => env('CLUB_ADDRESS', 'Vadakkencherry, Palakkad District, Kerala 678683'),
    'phone' => env('CLUB_PHONE', '+91 4922 255 000'),
    'email' => env('CLUB_EMAIL', 'info@malabarclub.com'),
    'gstin' => env('CLUB_GSTIN', '32AAAAA0000A1Z5'),
    'pan' => env('CLUB_PAN', 'AAAAA0000A'),

    /*
    |--------------------------------------------------------------------------
    | Club Business Day Cutoff Time
    |--------------------------------------------------------------------------
    |
    | Cutoff time for business-day attribution in 'HH:MM' or 'HH:MM:SS' format.
    | Default is '00:00' (local midnight).
    | With '00:00', a 01:30 AM transaction belongs to the new business date,
    | even if yesterday's cashier shift remains open.
    | With '03:00', a 01:30 AM transaction belongs to the previous business date.
    |
    */
    'business_day_cutoff_time' => env('CLUB_BUSINESS_DAY_CUTOFF', '00:00'),

    /*
    |--------------------------------------------------------------------------
    | Operating Timezone
    |--------------------------------------------------------------------------
    |
    | Authoritative timezone for the club operations (default Asia/Kolkata).
    |
    */
    'timezone' => env('CLUB_TIMEZONE', env('APP_TIMEZONE', 'Asia/Kolkata')),

    /*
    |--------------------------------------------------------------------------
    | Financial Year Start
    |--------------------------------------------------------------------------
    |
    | Initial default: 1 April ('04-01').
    |
    */
    'financial_year_start' => env('CLUB_FINANCIAL_YEAR_START', '04-01'),

    /*
    |--------------------------------------------------------------------------
    | POS Multi-Bill / Running Bar Tabs
    |--------------------------------------------------------------------------
    |
    | Maximum concurrent held/parked tabs per billing counter (default: 50).
    |
    */
    'pos' => [
        'max_held_tabs' => (int) env('CLUB_POS_MAX_HELD_TABS', 50),
    ],
];
