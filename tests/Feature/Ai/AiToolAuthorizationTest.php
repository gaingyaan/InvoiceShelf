<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Ai\Application\AiConfigurationService;
use App\Platform\Ai\Data\AiChatResponse;
use App\Platform\Ai\Drivers\AiDriverFactory;
use App\Platform\Ai\Models\AiMessage;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;
use Tests\Support\ScriptedAiDriver;

use function Pest\Laravel\postJson;

/**
 * The AI assistant must honour the SAME per-user Bouncer abilities as the rest
 * of the app: tools are hidden from the model when the user lacks the ability,
 * and executing one anyway returns a structured `unauthorized` error. Company
 * scoping is covered elsewhere; this file is specifically about per-user gating.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->owner = User::find(1);
    $this->companyId = $this->owner->companies()->first()->id;

    // A second user in the same company with NO abilities granted yet.
    $this->restricted = User::factory()->create();
    $this->restricted->companies()->attach($this->companyId);

    app(AiConfigurationService::class)->saveGlobalConfig([
        'ai_enabled' => 'YES',
        'ai_driver' => 'scripted',
        'ai_api_key' => 'test-key',
        'ai_chat_enabled' => 'YES',
        'ai_chat_model' => 'test-model',
    ]);

    AiDriverFactory::register('scripted', ScriptedAiDriver::class);
    ScriptedAiDriver::reset();
});

afterEach(fn () => ScriptedAiDriver::reset());

/**
 * Grant a Bouncer ability to a user within the company scope, mirroring how
 * CompanyService seeds the owner's abilities.
 */
function grantAbility(User $user, int $companyId, string $ability, ?string $model = null): void
{
    BouncerFacade::scope()->to($companyId);

    $model === null
        ? BouncerFacade::allow($user)->to($ability)
        : BouncerFacade::allow($user)->to($ability, $model);
}

test('tools the user lacks the ability for are hidden from the LLM', function () {
    grantAbility($this->restricted, $this->companyId, 'view-invoice', Invoice::class);

    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->restricted, ['*']);

    ScriptedAiDriver::setResponses([new AiChatResponse(message: 'ok')]);

    postJson('/api/v1/ai/chat', ['message' => 'hi'])->assertOk();

    $toolNames = collect(ScriptedAiDriver::$lastTools)->pluck('function.name');

    // Invoice tools visible (has view-invoice)...
    expect($toolNames)->toContain('search_invoices')
        ->and($toolNames)->toContain('get_invoice')
        ->and($toolNames)->toContain('list_overdue_invoices');

    // ...everything the user can't view is hidden.
    expect($toolNames)->not->toContain('search_customers')
        ->and($toolNames)->not->toContain('get_customer')
        ->and($toolNames)->not->toContain('rank_top_customers')
        ->and($toolNames)->not->toContain('list_recent_payments')
        ->and($toolNames)->not->toContain('search_items')
        ->and($toolNames)->not->toContain('list_expense_categories')
        ->and($toolNames)->not->toContain('get_company_stats');
});

test('a user with no abilities is offered no tools at all', function () {
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->restricted, ['*']);

    ScriptedAiDriver::setResponses([new AiChatResponse(message: 'ok')]);

    postJson('/api/v1/ai/chat', ['message' => 'hi'])->assertOk();

    expect(ScriptedAiDriver::$lastTools)->toBe([]);
});

test('executing an unauthorized tool returns an unauthorized error to the model', function () {
    grantAbility($this->restricted, $this->companyId, 'view-invoice', Invoice::class);

    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->restricted, ['*']);

    // The model is scripted to call a tool it was never offered (search_customers).
    ScriptedAiDriver::setResponses([
        new AiChatResponse(
            message: null,
            toolCalls: [[
                'id' => 'call_1',
                'name' => 'search_customers',
                'arguments' => ['query' => 'acme'],
            ]],
            finishReason: 'tool_calls',
        ),
        new AiChatResponse(message: 'done'),
    ]);

    postJson('/api/v1/ai/chat', ['message' => 'list every customer'])->assertOk();

    $toolMessage = AiMessage::where('role', 'tool')->latest('id')->first();

    expect($toolMessage)->not->toBeNull()
        ->and($toolMessage->content)->toContain('unauthorized');
});

test('a user with view-customer can use the customer tools', function () {
    grantAbility($this->restricted, $this->companyId, 'view-customer', Customer::class);

    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->restricted, ['*']);

    ScriptedAiDriver::setResponses([
        new AiChatResponse(
            message: null,
            toolCalls: [[
                'id' => 'call_1',
                'name' => 'search_customers',
                'arguments' => ['query' => ''],
            ]],
            finishReason: 'tool_calls',
        ),
        new AiChatResponse(message: 'Here are your customers.'),
    ]);

    postJson('/api/v1/ai/chat', ['message' => 'show customers'])->assertOk();

    $toolMessage = AiMessage::where('role', 'tool')->latest('id')->first();

    expect($toolMessage->content)->not->toContain('unauthorized')
        ->and($toolMessage->content)->toContain('customers');

    $toolNames = collect(ScriptedAiDriver::$lastTools)->pluck('function.name');
    expect($toolNames)->toContain('search_customers')
        ->and($toolNames)->not->toContain('search_invoices');
});

test('a fully-privileged owner is offered every tool, including stats', function () {
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->owner, ['*']);

    ScriptedAiDriver::setResponses([new AiChatResponse(message: 'ok')]);

    postJson('/api/v1/ai/chat', ['message' => 'hi'])->assertOk();

    $toolNames = collect(ScriptedAiDriver::$lastTools)->pluck('function.name');

    expect($toolNames)->toContain('search_invoices')
        ->and($toolNames)->toContain('search_customers')
        ->and($toolNames)->toContain('list_recent_payments')
        ->and($toolNames)->toContain('search_items')
        ->and($toolNames)->toContain('list_expense_categories')
        ->and($toolNames)->toContain('get_company_stats');
});
