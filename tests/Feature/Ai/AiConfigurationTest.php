<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Platform\Ai\Application\AiConfigurationService;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->companyId = $this->user->companies()->first()->id;

    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->user, ['*']);
});

test('admin can fetch AI driver list with metadata', function () {
    $response = getJson('/api/v1/ai/drivers')->assertOk();

    $drivers = collect($response->json('ai_drivers'));
    $openrouter = $drivers->firstWhere('value', 'openrouter');

    expect($openrouter)->not->toBeNull()
        ->and($openrouter['label'])->toBe('settings.ai.openrouter')
        ->and($openrouter['supported_roles'])->toContain('chat')
        ->and($openrouter['suggested_models'])->not->toBeEmpty();
});

test('admin can save and retrieve global AI config with api key masked in response', function () {
    postJson('/api/v1/ai/config', [
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'sk-or-super-secret',
        'ai_base_url' => 'https://openrouter.ai/api/v1',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'openai/gpt-4o',
        'ai_text_generation_enabled' => 'NO',
        'ai_text_generation_model' => '',
    ])->assertOk();

    $response = getJson('/api/v1/ai/config')->assertOk();

    // API key is masked in responses — never returned in plaintext
    expect($response->json('ai_api_key'))->toBe('********');
    expect($response->json('ai_enabled'))->toBe('YES');
    expect($response->json('ai_chat_model'))->toBe('openai/gpt-4o');
});

test('admin save preserves existing api key when masked placeholder is submitted', function () {
    // Initial save
    postJson('/api/v1/ai/config', [
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'original-key',
    ])->assertOk();

    // Second save submits the masked placeholder — key should be preserved
    postJson('/api/v1/ai/config', [
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => '********',
        'ai_chat_model' => 'openai/gpt-4o-mini',
    ])->assertOk();

    $service = app(AiConfigurationService::class);
    $config = $service->getGlobalConfig();

    expect($config['ai_api_key'])->toBe('original-key')
        ->and($config['ai_chat_model'])->toBe('openai/gpt-4o-mini');
});

test('company save respects use_custom_ai_config toggle OFF', function () {
    postJson('/api/v1/company/ai/config', [
        'use_custom_ai_config' => 'NO',
        'ai_api_key' => 'should-not-be-saved',
    ])->assertOk();

    $raw = CompanySetting::getSettings(['use_custom_ai_config', 'company_ai_api_key'], $this->companyId)->all();
    expect($raw['use_custom_ai_config'] ?? null)->toBe('NO');
    expect($raw['company_ai_api_key'] ?? null)->toBeNull();
});

test('company save with toggle ON persists company-specific driver fields', function () {
    postJson('/api/v1/company/ai/config', [
        'use_custom_ai_config' => 'YES',
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'company-key',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'anthropic/claude-3.5-sonnet',
    ])->assertOk();

    $response = getJson('/api/v1/company/ai/config')->assertOk();

    expect($response->json('use_custom_ai_config'))->toBe('YES')
        ->and($response->json('ai_chat_model'))->toBe('anthropic/claude-3.5-sonnet')
        ->and($response->json('ai_api_key'))->toBe('********');  // masked
});

test('bootstrap response surfaces ai flags based on resolution', function () {
    // No config — AI should be disabled
    $response = getJson('/api/v1/bootstrap')->assertOk();
    expect($response->json('ai.enabled'))->toBeFalse();

    // Enable globally with chat enabled
    app(AiConfigurationService::class)->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'key',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'openai/gpt-4o',
        'ai_text_generation_enabled' => 'NO',
    ]);

    $response = getJson('/api/v1/bootstrap')->assertOk();
    expect($response->json('ai.enabled'))->toBeTrue()
        ->and($response->json('ai.chat_enabled'))->toBeTrue()
        ->and($response->json('ai.text_generation_enabled'))->toBeFalse();
});

test('bootstrap response hides ai when company opts out via override', function () {
    app(AiConfigurationService::class)->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'key',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'openai/gpt-4o',
    ]);

    app(AiConfigurationService::class)->saveCompanyConfig($this->companyId, [
        'use_custom_ai_config' => 'YES',
        'ai_enabled' => 'NO',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'unused',
    ]);

    $response = getJson('/api/v1/bootstrap')->assertOk();

    expect($response->json('ai.enabled'))->toBeFalse();
});
