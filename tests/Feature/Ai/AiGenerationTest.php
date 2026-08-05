<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Ai\Application\AiConfigurationService;
use App\Platform\Ai\Contracts\AiDriver;
use App\Platform\Ai\Data\AiChatResponse;
use App\Platform\Ai\Drivers\AiDriverFactory;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * Scripted driver reused from AiChatFlowTest's strategy — tracks which prompt
 * went into textCompletion() and echoes a canned reply.
 */
class TextGenDriver extends AiDriver
{
    public static ?string $lastPrompt = null;

    public static ?string $lastModel = null;

    public static string $reply = 'generated text';

    public static int $callCount = 0;

    public function chatCompletion(array $messages, string $model, array $tools = [], array $options = []): AiChatResponse
    {
        return new AiChatResponse(message: self::$reply);
    }

    public function textCompletion(string $prompt, string $model, array $options = []): string
    {
        self::$lastPrompt = $prompt;
        self::$lastModel = $model;
        self::$callCount++;

        return self::$reply;
    }

    public function validateConnection(): array
    {
        return ['ok' => true];
    }
}

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->companyId = $this->user->companies()->first()->id;

    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->user, ['*']);

    AiDriverFactory::register('textgen', TextGenDriver::class);
    TextGenDriver::$lastPrompt = null;
    TextGenDriver::$lastModel = null;
    TextGenDriver::$callCount = 0;
    TextGenDriver::$reply = 'generated text';

    app(AiConfigurationService::class)->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'textgen',
        'ai_api_key' => 'test-key',
        'ai_text_generation_enabled' => 'YES',
        'ai_text_generation_model' => 'test-textgen-model',
    ]);
});

test('generate endpoint returns generated text for a valid prompt', function () {
    TextGenDriver::$reply = 'Dear customer, your invoice is overdue.';

    $response = postJson('/api/v1/ai/generate', [
        'prompt' => 'Write a late payment reminder',
    ])->assertOk();

    expect($response->json('text'))->toBe('Dear customer, your invoice is overdue.');
    expect(TextGenDriver::$lastModel)->toBe('test-textgen-model');
    expect(TextGenDriver::$lastPrompt)->toContain('Write a late payment reminder');
});

test('generate endpoint includes context in the prompt when provided', function () {
    postJson('/api/v1/ai/generate', [
        'prompt' => 'Rewrite this in a friendlier tone',
        'context' => 'PAY US NOW OR ELSE',
    ])->assertOk();

    expect(TextGenDriver::$lastPrompt)
        ->toContain('Context (current content the user is working with):')
        ->toContain('PAY US NOW OR ELSE')
        ->toContain('Rewrite this in a friendlier tone');
});

test('generate endpoint omits context section when no context is supplied', function () {
    postJson('/api/v1/ai/generate', [
        'prompt' => 'Hello world',
    ])->assertOk();

    expect(TextGenDriver::$lastPrompt)->not->toContain('Context (current content');
});

test('generate endpoint rejects when AI is globally disabled', function () {
    app(AiConfigurationService::class)->saveGlobalConfig(['ai_enabled' => 'NO']);

    postJson('/api/v1/ai/generate', ['prompt' => 'Hi'])->assertStatus(422);
});

test('generate endpoint rejects when text_generation role is disabled', function () {
    app(AiConfigurationService::class)->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'textgen',
        'ai_api_key' => 'k',
        'ai_text_generation_enabled' => 'NO',  // <— off
        'ai_text_generation_model' => 'test',
    ]);

    postJson('/api/v1/ai/generate', ['prompt' => 'Hi'])->assertStatus(422);
});

test('generate endpoint validates prompt length', function () {
    // Empty prompt → validation error
    postJson('/api/v1/ai/generate', ['prompt' => ''])->assertStatus(422);

    // Prompt over 4000 chars → validation error
    postJson('/api/v1/ai/generate', ['prompt' => str_repeat('a', 4001)])->assertStatus(422);

    // Context over 20k chars → validation error
    postJson('/api/v1/ai/generate', [
        'prompt' => 'Hi',
        'context' => str_repeat('x', 20001),
    ])->assertStatus(422);
});

test('generate endpoint trims whitespace from the driver response', function () {
    TextGenDriver::$reply = "  \n\n  spaced out reply  \n\n  ";

    $response = postJson('/api/v1/ai/generate', [
        'prompt' => 'anything',
    ])->assertOk();

    expect($response->json('text'))->toBe('spaced out reply');
});
