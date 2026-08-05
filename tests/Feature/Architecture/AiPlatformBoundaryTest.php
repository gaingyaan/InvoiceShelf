<?php

use App\Platform\Ai\AiServiceProvider;
use App\Platform\Ai\Application\AiToolRegistry;
use App\Platform\Ai\Drivers\OpenRouterDriver;
use App\Platform\Ai\Http\Company\ChatController;
use App\Platform\Ai\Models\AiConversation;
use App\Platform\Ai\Policies\AiConversationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use InvoiceShelf\Modules\Registry;

test('the ai platform owns its provider extensions and authorization', function () {
    expect(app()->getProviders(AiServiceProvider::class))->toHaveCount(1)
        ->and(app(AiToolRegistry::class))->toBe(app(AiToolRegistry::class))
        ->and(Gate::getPolicyFor(AiConversation::class))->toBeInstanceOf(AiConversationPolicy::class)
        ->and(Gate::has('manage ai config'))->toBeTrue()
        ->and(Gate::has('use ai'))->toBeTrue()
        ->and(RateLimiter::limiter('ai'))->not->toBeNull()
        ->and(Registry::driverMeta('ai', 'openrouter')['class'])->toBe(OpenRouterDriver::class);

    expect(class_exists('App\\Providers\\AiServiceProvider'))->toBeFalse()
        ->and(class_exists('App\\Services\\Ai\\AiAssistantService'))->toBeFalse()
        ->and(class_exists('App\\Support\\Ai\\AiDriver'))->toBeFalse()
        ->and(class_exists('App\\Policies\\AiConversationPolicy'))->toBeFalse();
});

test('the ai platform preserves its public routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains($route->uri(), '/ai/'))
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe(collect([
        'DELETE api/v1/ai/conversations/{id}',
        'GET|HEAD api/v1/ai/config',
        'GET|HEAD api/v1/ai/conversations',
        'GET|HEAD api/v1/ai/conversations/{id}',
        'GET|HEAD api/v1/ai/drivers',
        'GET|HEAD api/v1/company/ai/config',
        'GET|HEAD api/v1/installation/ai/config',
        'PATCH api/v1/ai/conversations/{id}',
        'POST api/v1/ai/chat',
        'POST api/v1/ai/config',
        'POST api/v1/ai/generate',
        'POST api/v1/ai/test',
        'POST api/v1/company/ai/config',
        'POST api/v1/company/ai/test',
        'POST api/v1/installation/ai/config',
    ])->sort()->values()->all());

    $chat = $routes->get('POST api/v1/ai/chat');

    expect($chat->getActionName())->toBe(ChatController::class)
        ->and($chat->gatherMiddleware())->toContain('auth:sanctum', 'company', 'bouncer', 'throttle:ai');
});
