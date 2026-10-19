<?php

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use function Pest\Faker\fake;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('company setting belongs to company', function () {
    $setting = CompanySetting::factory()->create();

    $this->assertTrue($setting->company()->exists());
});

test('set settings', function () {
    $key = fake()->name;

    $value = fake()->word;

    $company = Company::factory()->create();

    CompanySetting::setSettings([$key => $value], $company->id);

    $response = CompanySetting::getSetting($key, $company->id);

    $this->assertEquals($value, $response);
});

test('get settings', function () {
    $key = fake()->name;

    $value = fake()->word;

    $company = Company::factory()->create();

    CompanySetting::setSettings([$key => $value], $company->id);

    $response = CompanySetting::getSettings([$key], $company->id);

    $this->assertEquals([$key => $value], $response->toArray());
});

test('company setting uses in-memory cache and flushCache clears it', function () {
    $key = fake()->name;
    $value = fake()->word;
    $company = Company::factory()->create();

    CompanySetting::setSettings([$key => $value], $company->id);

    // Warm cache
    DB::flushQueryLog();
    DB::enableQueryLog();
    $cached1 = CompanySetting::getSetting($key, $company->id);
    $queriesFirst = count(DB::getQueryLog());
    $this->assertEquals(1, $queriesFirst);

    // Second call should hit in-memory cache with 0 queries
    DB::flushQueryLog();
    $cached2 = CompanySetting::getSetting($key, $company->id);
    $queriesSecond = count(DB::getQueryLog());
    $this->assertEquals(0, $queriesSecond);
    $this->assertEquals($value, $cached2);

    // After flushCache, a query should run again
    CompanySetting::flushCache();
    DB::flushQueryLog();
    $cached3 = CompanySetting::getSetting($key, $company->id);
    $queriesThird = count(DB::getQueryLog());
    $this->assertEquals(1, $queriesThird);
    $this->assertEquals($value, $cached3);
});
