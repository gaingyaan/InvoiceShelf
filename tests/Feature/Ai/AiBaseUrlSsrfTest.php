<?php

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * The admin/owner-supplied AI base URL must not be allowed to point the server
 * (with the bearer token attached) at a private or reserved address (SSRF).
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->companyId = $this->user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->user, ['*']);
});

test('admin AI config rejects a private base URL', function () {
    postJson('/api/v1/ai/config', [
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'sk-test',
        'ai_base_url' => 'http://169.254.169.254/v1',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'openai/gpt-4o',
    ])->assertStatus(422)->assertJsonValidationErrors('ai_base_url');
});

test('admin AI config still accepts a public base URL', function () {
    postJson('/api/v1/ai/config', [
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'sk-test',
        'ai_base_url' => 'https://openrouter.ai/api/v1',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'openai/gpt-4o',
    ])->assertOk();
});

test('admin AI test-connection rejects a private base URL', function () {
    postJson('/api/v1/ai/test', [
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'sk-test',
        'ai_base_url' => 'http://127.0.0.1:11434',
    ])->assertStatus(422)->assertJsonValidationErrors('ai_base_url');
});

test('company AI config rejects a private base URL', function () {
    postJson('/api/v1/company/ai/config', [
        'use_custom_ai_config' => 'YES',
        'ai_enabled' => 'YES',
        'ai_driver' => 'openrouter',
        'ai_api_key' => 'company-key',
        'ai_base_url' => 'http://10.0.0.5',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'openai/gpt-4o',
    ])->assertStatus(422)->assertJsonValidationErrors('ai_base_url');
});
