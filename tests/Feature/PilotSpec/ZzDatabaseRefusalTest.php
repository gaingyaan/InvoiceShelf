<?php

// Pilot behavioural suite — the non-empty-database refusal.
// Kept in its own file, alphabetically last: exercising the refusal swaps the
// application's active database connection for the remainder of the PHP
// process, which would poison any test file running after it.

use function Pest\Laravel\postJson;

// Swaps the process's DB connection — excluded from default runs (phpunit.xml),
// executed standalone as the last step of each verification pass.
uses()->group('isolated');

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    // CI has no .env; seed one the way an installer would so the
    // env-writing paths under test have a real file to work against.
    $this->envExisted = file_exists(base_path('.env'));
    if (! $this->envExisted) {
        copy(base_path('.env.example'), base_path('.env'));
    }
    $this->envBackup = file_get_contents(base_path('.env'));
});

afterEach(function () {
    if ($this->envExisted) {
        file_put_contents(base_path('.env'), $this->envBackup);
    } else {
        @unlink(base_path('.env'));
    }
});

it('refuses a database that already contains data, without touching the environment file', function () {
    $path = storage_path('framework/testing/pilot-nonempty.sqlite');
    @mkdir(dirname($path), 0775, true);
    @unlink($path);
    $db = new SQLite3($path);
    $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
    $db->close();

    $response = postJson('/api/v1/installation/database/config', [
        'app_url' => 'http://pilot.test',
        'database_connection' => 'sqlite',
        'database_name' => $path,
    ])->assertOk()->json();

    expect($response['error'])->toBe('database_should_be_empty');
    expect(file_get_contents(base_path('.env')))->toBe($this->envBackup);

    @unlink($path);
});
