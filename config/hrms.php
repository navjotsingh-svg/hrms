<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company Branding
    |--------------------------------------------------------------------------
    |
    | Set COMPANY_LOGO in .env to a public path, e.g. images/company-logo.png
    | Place the file in public/images/. If empty, the default HRMS logo is shown.
    |
    */

    'company_name' => env('COMPANY_NAME', 'HRMS'),

    'company_logo' => env('COMPANY_LOGO'),

    /*
    |--------------------------------------------------------------------------
    | People Module
    |--------------------------------------------------------------------------
    |
    | Set HRMS_PEOPLE_MENU_ENABLED=true in .env when ready to show People in sidebar.
    |
    */

    'people_menu_enabled' => env('HRMS_PEOPLE_MENU_ENABLED', false),

];
