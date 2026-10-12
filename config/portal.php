<?php

return [

    /* Name shown in the sidebar, page titles and login screens. */
    'name' => env('PORTAL_NAME', 'Konektado IT Solutions'),

    /*
    | Allow visitors to create their own "User" accounts at /register.
    | Admin and Super Admin accounts can never be created this way.
    */
    'allow_registration' => (bool) env('PORTAL_ALLOW_REGISTRATION', true),

    /*
    | Password given to the accounts created by `php artisan db:seed`.
    | Outside local development the seeder refuses to run with the default.
    */
    'seed_password' => env('PORTAL_SEED_PASSWORD', 'password'),

];
