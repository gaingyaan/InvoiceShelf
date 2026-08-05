<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Ai\Application\AiConfigurationService;
use App\Platform\Ai\Contracts\AiDriver;
use App\Platform\Ai\Data\AiChatResponse;
use App\Platform\Ai\Drivers\AiDriverFactory;
use App\Platform\Ai\Exceptions\AiException;
use App\Platform\Ai\Models\AiConversation;
use App\Platform\Ai\Models\AiMessage;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ScriptedAiDriver;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->companyId = $this->user->companies()->first()->id;

    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->user, ['*']);

    // Enable AI globally so resolveForCompany returns a config
    app(AiConfigurationService::class)->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'scripted',
        'ai_api_key' => 'test-key',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'test-model',
    ]);

    // Register the scripted driver so AiDriverFactory returns it for 'scripted'
    AiDriverFactory::register('scripted', ScriptedAiDriver::class);
    ScriptedAiDriver::reset();
});

afterEach(function () {
    ScriptedAiDriver::reset();
});

test('chat endpoint creates a new conversation and persists user + assistant messages', function () {
    ScriptedAiDriver::setResponses([
        new AiChatResponse(message: 'Hello back!'),
    ]);

    $response = postJson('/api/v1/ai/chat', [
        'message' => 'Hi assistant',
    ])->assertOk();

    $conversationId = $response->json('conversation.id');
    expect($conversationId)->toBeInt();
    expect($response->json('message.content'))->toBe('Hello back!');
    expect($response->json('message.role'))->toBe('assistant');

    // Both user and assistant messages should be persisted
    $conversation = AiConversation::find($conversationId);
    expect($conversation->user_id)->toBe($this->user->id);
    expect($conversation->company_id)->toBe($this->companyId);

    $messages = $conversation->messages()->orderBy('created_at')->get();
    expect($messages->pluck('role')->all())->toBe(['user', 'assistant']);
    expect($messages[0]->content)->toBe('Hi assistant');
    expect($messages[1]->content)->toBe('Hello back!');
});

test('chat endpoint runs a tool-call loop and returns the final answer', function () {
    // Round 1: LLM requests search_invoices
    // Round 2: LLM produces final text
    ScriptedAiDriver::setResponses([
        new AiChatResponse(
            message: null,
            toolCalls: [[
                'id' => 'call_1',
                'name' => 'search_invoices',
                'arguments' => ['limit' => 5],
            ]],
            finishReason: 'tool_calls',
        ),
        new AiChatResponse(message: 'Here are your invoices.'),
    ]);

    $response = postJson('/api/v1/ai/chat', [
        'message' => 'Show me recent invoices',
    ])->assertOk();

    expect($response->json('message.content'))->toBe('Here are your invoices.');
    expect(ScriptedAiDriver::$callCount)->toBe(2);

    // Conversation should now contain: user, assistant(tool_calls), tool(result), assistant(final)
    $conversation = AiConversation::find($response->json('conversation.id'));
    $roles = $conversation->messages()->orderBy('id')->pluck('role')->all();
    expect($roles)->toBe(['user', 'assistant', 'tool', 'assistant']);
});

test('chat endpoint caps runaway tool-call loops at MAX_TOOL_ITERATIONS', function () {
    // All responses are tool_calls — simulating a model stuck in a loop
    $loopResponse = new AiChatResponse(
        message: null,
        toolCalls: [[
            'id' => 'call_loop',
            'name' => 'search_invoices',
            'arguments' => [],
        ]],
        finishReason: 'tool_calls',
    );
    // Queue 10 of them — way more than the 5-iteration cap
    ScriptedAiDriver::setResponses(array_fill(0, 10, $loopResponse));

    $response = postJson('/api/v1/ai/chat', [
        'message' => 'Loop forever',
    ])->assertOk();

    // We should hit the cap and the service should return a graceful error message.
    expect($response->json('message.content'))->toContain('tool-call budget');
    // 5 calls + the "I give up" turn shouldn't exceed the hard cap
    expect(ScriptedAiDriver::$callCount)->toBeLessThanOrEqual(5);
});

test('chat endpoint persists an error message when the driver throws', function () {
    ScriptedAiDriver::setResponses([]);  // no responses queued → driver returns default

    // Inject a failing driver via the factory
    $failingDriver = new class('fake', []) extends AiDriver
    {
        public function chatCompletion(array $messages, string $model, array $tools = [], array $options = []): AiChatResponse
        {
            throw new AiException('test failure', 'server_error');
        }

        public function textCompletion(string $prompt, string $model, array $options = []): string
        {
            return '';
        }

        public function validateConnection(): array
        {
            return [];
        }
    };
    AiDriverFactory::register('failing', $failingDriver::class);

    app(AiConfigurationService::class)->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'failing',
        'ai_api_key' => 'k',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'test',
    ]);

    $response = postJson('/api/v1/ai/chat', [
        'message' => 'Hello',
    ])->assertOk();

    expect($response->json('message.content'))->toContain('Error:');
});

test('chat endpoint rejects when AI is disabled for the company', function () {
    app(AiConfigurationService::class)->saveGlobalConfig([
        'ai_enabled' => 'NO',
    ]);

    postJson('/api/v1/ai/chat', [
        'message' => 'Hi',
    ])->assertStatus(422);
});

test('chat endpoint rejects when chat role is disabled even if AI is enabled', function () {
    app(AiConfigurationService::class)->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'scripted',
        'ai_api_key' => 'k',
        'ai_chat_enabled' => 'NO',  // <— off
        'ai_chat_model' => 'test',
    ]);

    postJson('/api/v1/ai/chat', [
        'message' => 'Hi',
    ])->assertStatus(422);
});

test('conversation index returns only the current user\'s conversations', function () {
    // Create a conversation for the authenticated user
    ScriptedAiDriver::setResponses([new AiChatResponse(message: 'hi')]);
    postJson('/api/v1/ai/chat', ['message' => 'First message'])->assertOk();

    // Create a conversation for a different user in the same company — should NOT be visible
    $otherUser = User::factory()->create();
    $otherUser->companies()->attach($this->companyId);
    AiConversation::create([
        'company_id' => $this->companyId,
        'user_id' => $otherUser->id,
        'title' => 'Other users secret chat',
    ]);

    $response = getJson('/api/v1/ai/conversations')->assertOk();

    $titles = collect($response->json('conversations'))->pluck('title');
    expect($titles)->not->toContain('Other users secret chat');
});

test('conversation show enforces ownership via policy', function () {
    $otherUser = User::factory()->create();
    $otherUser->companies()->attach($this->companyId);

    $foreignConvo = AiConversation::create([
        'company_id' => $this->companyId,
        'user_id' => $otherUser->id,
        'title' => 'Not yours',
    ]);

    getJson("/api/v1/ai/conversations/{$foreignConvo->id}")->assertForbidden();
});

test('conversation delete cascades messages', function () {
    ScriptedAiDriver::setResponses([new AiChatResponse(message: 'reply')]);
    $sendResponse = postJson('/api/v1/ai/chat', ['message' => 'First'])->assertOk();
    $conversationId = $sendResponse->json('conversation.id');

    // There should now be 2 messages
    expect(AiMessage::where('conversation_id', $conversationId)->count())->toBe(2);

    $this->deleteJson("/api/v1/ai/conversations/{$conversationId}")->assertOk();

    expect(AiConversation::find($conversationId))->toBeNull();
    expect(AiMessage::where('conversation_id', $conversationId)->count())->toBe(0);
});
