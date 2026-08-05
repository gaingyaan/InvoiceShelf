<?php

namespace App\Platform\Ai;

use App\Platform\Ai\Application\AiToolRegistry;
use App\Platform\Ai\Application\Tools\GetCompanyStatsTool;
use App\Platform\Ai\Application\Tools\GetCustomerTool;
use App\Platform\Ai\Application\Tools\GetInvoiceTool;
use App\Platform\Ai\Application\Tools\ListExpenseCategoriesTool;
use App\Platform\Ai\Application\Tools\ListOverdueInvoicesTool;
use App\Platform\Ai\Application\Tools\ListRecentPaymentsTool;
use App\Platform\Ai\Application\Tools\RankExpenseCategoriesTool;
use App\Platform\Ai\Application\Tools\RankTopCustomersTool;
use App\Platform\Ai\Application\Tools\RankTopItemsTool;
use App\Platform\Ai\Application\Tools\SearchCustomersTool;
use App\Platform\Ai\Application\Tools\SearchInvoicesTool;
use App\Platform\Ai\Application\Tools\SearchItemsTool;
use App\Platform\Ai\Drivers\OpenRouterDriver;
use App\Platform\Ai\Models\AiConversation;
use App\Platform\Ai\Policies\AiAccessPolicy;
use App\Platform\Ai\Policies\AiConversationPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvoiceShelf\Modules\Registry;

/**
 * Binds the AI tool registry as a singleton and registers every built-in
 * read-only tool the chat assistant can call.
 *
 * Modules that ship additional tools should extend the registry from their
 * own ServiceProvider::boot() by resolving AiToolRegistry from the container
 * and calling register() on it.
 */
class AiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(AiConversation::class, AiConversationPolicy::class);
        Gate::define('manage ai config', [AiAccessPolicy::class, 'manageConfiguration']);
        Gate::define('use ai', [AiAccessPolicy::class, 'use']);

        RateLimiter::for('ai', function (Request $request) {
            $user = $request->user();
            $companyId = $request->header('company') ?? 'noop';
            $key = $user ? "{$user->id}:{$companyId}" : $request->ip();

            return Limit::perMinute(30)->by($key);
        });

        Registry::registerAiDriver('openrouter', [
            'class' => OpenRouterDriver::class,
            'label' => 'settings.ai.openrouter',
            'website' => 'https://openrouter.ai',
            'default_base_url' => 'https://openrouter.ai/api/v1',
            'supported_roles' => ['chat', 'text_generation'],
            'suggested_models' => [
                ['value' => 'anthropic/claude-sonnet-4.6', 'label' => 'Anthropic Claude Sonnet 4.6'],
                ['value' => 'anthropic/claude-haiku-4.5', 'label' => 'Anthropic Claude Haiku 4.5'],
                ['value' => 'anthropic/claude-opus-4.6', 'label' => 'Anthropic Claude Opus 4.6'],
                ['value' => 'openai/gpt-5.4', 'label' => 'OpenAI GPT-5.4'],
                ['value' => 'openai/gpt-5.4-mini', 'label' => 'OpenAI GPT-5.4 mini'],
                ['value' => 'google/gemini-3.1-pro-preview', 'label' => 'Google Gemini 3.1 Pro (preview)'],
                ['value' => 'google/gemini-3.1-flash-lite-preview', 'label' => 'Google Gemini 3.1 Flash Lite (preview)'],
                ['value' => 'z-ai/glm-5.1', 'label' => 'Z.AI GLM 5.1'],
                ['value' => 'z-ai/glm-4.7-flash', 'label' => 'Z.AI GLM 4.7 Flash'],
            ],
            'config_fields' => [
                [
                    'key' => 'base_url',
                    'type' => 'text',
                    'label' => 'settings.ai.base_url',
                    'default' => 'https://openrouter.ai/api/v1',
                ],
            ],
        ]);
    }

    public function register(): void
    {
        $this->app->singleton(AiToolRegistry::class, function (Application $app): AiToolRegistry {
            $registry = new AiToolRegistry;

            // Built-in read-only tools (order is presentation-only; the LLM picks).
            $registry->register(new SearchInvoicesTool);
            $registry->register(new GetInvoiceTool);
            $registry->register(new ListOverdueInvoicesTool);
            $registry->register(new SearchCustomersTool);
            $registry->register(new GetCustomerTool);
            $registry->register(new ListRecentPaymentsTool);
            $registry->register(new SearchItemsTool);
            $registry->register(new ListExpenseCategoriesTool);
            $registry->register(new GetCompanyStatsTool);

            // Ranking tools — group-by aggregates the individual-record
            // tools above can't express.
            $registry->register(new RankTopCustomersTool);
            $registry->register(new RankTopItemsTool);
            $registry->register(new RankExpenseCategoriesTool);

            return $registry;
        });
    }
}
