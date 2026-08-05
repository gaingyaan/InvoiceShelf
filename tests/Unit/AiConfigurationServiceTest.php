<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Platform\Ai\Application\AiConfigurationService;
use App\Platform\Ai\Drivers\OpenRouterDriver;
use App\Platform\Operations\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->service = new AiConfigurationService;
    $this->user = User::find(1);
    $this->companyId = $this->user->companies()->first()->id;
});

test('saving global config encrypts the api key at rest', function () {
    $this->service->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'sk-or-secret-key',
        'ai_base_url' => 'https://openrouter.ai/api/v1',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'openai/gpt-4o',
        'ai_text_generation_enabled' => 'NO',
        'ai_text_generation_model' => '',
    ]);

    $storedCiphertext = Setting::getSetting('ai_api_key');
    expect($storedCiphertext)->not->toBe('sk-or-secret-key');
    expect(Crypt::decryptString($storedCiphertext))->toBe('sk-or-secret-key');
});

test('reading global config decrypts the api key', function () {
    $this->service->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'sk-or-secret-key',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'openai/gpt-4o',
    ]);

    $config = $this->service->getGlobalConfig();

    expect($config['ai_api_key'])->toBe('sk-or-secret-key');
    expect($config['ai_enabled'])->toBe('YES');
    expect($config['ai_chat_model'])->toBe('openai/gpt-4o');
});

test('company save with toggle OFF only persists the toggle and discards driver fields', function () {
    $this->service->saveCompanyConfig($this->companyId, [
        'use_custom_ai_config' => 'NO',
        'ai_api_key' => 'this-should-not-be-stored',
        'ai_driver' => 'openrouter',
    ]);

    $raw = CompanySetting::getSettings(['use_custom_ai_config', 'company_ai_api_key'], $this->companyId)->all();

    expect($raw['use_custom_ai_config'] ?? null)->toBe('NO');
    expect($raw['company_ai_api_key'] ?? null)->toBeNull();
});

test('company save with toggle ON encrypts and persists driver fields', function () {
    $this->service->saveCompanyConfig($this->companyId, [
        'use_custom_ai_config' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'company-specific-key',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'anthropic/claude-3.5-sonnet',
    ]);

    $config = $this->service->getCompanyConfig($this->companyId);

    expect($config['use_custom_ai_config'])->toBe('YES');
    expect($config['ai_api_key'])->toBe('company-specific-key');
    expect($config['ai_chat_model'])->toBe('anthropic/claude-3.5-sonnet');
});

test('resolveForCompany returns null when global ai_enabled is NO', function () {
    $this->service->saveGlobalConfig([
        'ai_enabled' => 'NO',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'key',
    ]);

    expect($this->service->resolveForCompany($this->companyId))->toBeNull();
});

test('resolveForCompany returns null when company opts out via override', function () {
    $this->service->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'global-key',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'openai/gpt-4o',
    ]);

    // Company uses custom config but explicitly disables AI
    $this->service->saveCompanyConfig($this->companyId, [
        'use_custom_ai_config' => 'YES',
        'ai_enabled' => 'NO',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'unused',
    ]);

    expect($this->service->resolveForCompany($this->companyId))->toBeNull();
});

test('resolveForCompany returns global config when company has no custom override', function () {
    $this->service->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'global-key',
        'ai_base_url' => 'https://global.example.com/v1',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'openai/gpt-4o',
    ]);

    $resolved = $this->service->resolveForCompany($this->companyId);

    expect($resolved)->not->toBeNull();
    expect($resolved['ai_api_key'])->toBe('global-key');
    expect($resolved['ai_base_url'])->toBe('https://global.example.com/v1');
    expect($resolved['chat_enabled'])->toBeTrue();
});

test('resolveForCompany returns company config when use_custom_ai_config is YES', function () {
    $this->service->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'global-key',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'openai/gpt-4o',
    ]);

    $this->service->saveCompanyConfig($this->companyId, [
        'use_custom_ai_config' => 'YES',
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'company-key',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'anthropic/claude-3.5-sonnet',
    ]);

    $resolved = $this->service->resolveForCompany($this->companyId);

    expect($resolved)->not->toBeNull();
    expect($resolved['ai_api_key'])->toBe('company-key');
    expect($resolved['ai_chat_model'])->toBe('anthropic/claude-3.5-sonnet');
    expect($resolved['chat_enabled'])->toBeTrue();
});

test('makeDriver returns null when ai is disabled', function () {
    $this->service->saveGlobalConfig(['ai_enabled' => 'NO']);

    expect($this->service->makeDriver($this->companyId))->toBeNull();
});

test('makeDriver instantiates a driver from the resolved config', function () {
    $this->service->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'test-key',
    ]);

    $driver = $this->service->makeDriver($this->companyId);

    expect($driver)->toBeInstanceOf(OpenRouterDriver::class);
});

test('listDrivers returns driver metadata from the Registry', function () {
    $drivers = $this->service->listDrivers();

    expect($drivers)->not->toBeEmpty();

    $openrouter = collect($drivers)->firstWhere('value', 'openrouter');
    expect($openrouter)->not->toBeNull();
    expect($openrouter['label'])->toBe('settings.ai.openrouter');
    expect($openrouter['supported_roles'])->toContain('chat')->toContain('text_generation');
});
