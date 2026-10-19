<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use function Pest\Faker\fake;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('set setting', function () {
    $key = fake()->name;

    $value = fake()->word;

    Setting::setSetting($key, $value);

    $response = Setting::getSetting($key);

    $this->assertEquals($value, $response);
});

test('get setting uses in-memory cache and flushCache clears it', function () {
    $key = fake()->name;
    $value = fake()->word;
    Setting::setSetting($key, $value);

    // Warm cache
    DB::flushQueryLog();
    DB::enableQueryLog();
    $cached1 = Setting::getSetting($key);
    $queriesFirst = count(DB::getQueryLog());
    $this->assertEquals(1, $queriesFirst);

    // Second call should hit in-memory cache with 0 additional queries
    DB::flushQueryLog();
    $cached2 = Setting::getSetting($key);
    $queriesSecond = count(DB::getQueryLog());
    $this->assertEquals(0, $queriesSecond);
    $this->assertEquals($value, $cached2);

    // After flushCache, a query should run again
    Setting::flushCache();
    DB::flushQueryLog();
    $cached3 = Setting::getSetting($key);
    $queriesThird = count(DB::getQueryLog());
    $this->assertEquals(1, $queriesThird);
    $this->assertEquals($value, $cached3);
});
