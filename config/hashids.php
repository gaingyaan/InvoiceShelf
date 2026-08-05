<?php

/**
 * Hashids connection config (per model class).
 *
 * Wired by App\Support\Hashids\HashidsServiceProvider using the hashids/hashids package.
 */

use App\Support\Hashids\HashidConnection;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the connections below you wish to use as
    | your default connection for all work. Of course, you may use many
    | connections at once using the manager class.
    |
    */

    'default' => 'main',

    /*
    |--------------------------------------------------------------------------
    | Hashids Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the connections setup for your application. Example
    | configuration has been included, but you may add as many connections as
    | you would like.
    |
    */

    'connections' => [
        HashidConnection::Invoice->value => [
            'salt' => 'App\\Models\\Invoice'.config('app.key'),
            'length' => 20,
            'alphabet' => 'XKAR7m8jD2bqP9OSVeNGiYL465T10zhfWuc3',
        ],
        HashidConnection::Estimate->value => [
            'salt' => 'App\\Models\\Estimate'.config('app.key'),
            'length' => 20,
            'alphabet' => 'yJW2P79M8rCHsVq5zbn1fXl6IUt3dAekGo40',
        ],
        HashidConnection::Payment->value => [
            'salt' => 'App\\Models\\Payment'.config('app.key'),
            'length' => 20,
            'alphabet' => 'aqW3eR2Icf0jp65Gl7UVS1dhyb8Mn9XKTZ4O',
        ],
        HashidConnection::Company->value => [
            'salt' => 'App\\Models\\Company'.config('app.key'),
            'length' => 20,
            'alphabet' => 's0D7xOFYEqn2uKJm3Pr9g8Cz46A1iHLBTVW5',
        ],
        HashidConnection::EmailLog->value => [
            'salt' => 'App\\Models\\EmailLog'.config('app.key'),
            'length' => 20,
            'alphabet' => 'BA5tJUVNPe93fCq6DHlY2x4ZO1Kg7i8wSm0R',
        ],
        HashidConnection::Transaction->value => [
            'salt' => 'App\\Models\\Transaction'.config('app.key'),
            'length' => 20,
            'alphabet' => 'ADyWE86Cg7jF23vS0bonXrZ5KLH9puIQ4M1T',
        ],
    ],
];
