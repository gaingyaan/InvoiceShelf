<?php

use App\Events\ModuleDisabledEvent;
use App\Events\ModuleEnabledEvent;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

    $user = User::findOrFail(1);
    $this->withHeader('company', $user->companies()->firstOrFail()->id);
    Sanctum::actingAs($user, ['*']);
});

it('disables a module when its runtime files are missing', function () {
    Event::fake([ModuleDisabledEvent::class]);
    $module = missingRuntimeModule(enabled: true);

    postJson("/api/v1/modules/{$module->name}/disable")
        ->assertOk()
        ->assertJsonPath('success', true);

    $module->refresh();

    expect($module->installed)->toBeFalse()
        ->and($module->enabled)->toBeFalse()
        ->and($module->state)->toBe('failed')
        ->and($module->last_error)->toBe('module_runtime_missing')
        ->and($module->last_failed_at)->not->toBeNull();

    Event::assertDispatched(ModuleDisabledEvent::class);
});

it('returns a conflict and repairs state when enabling a module with missing runtime files', function () {
    Event::fake([ModuleEnabledEvent::class]);
    $module = missingRuntimeModule(enabled: false);

    postJson("/api/v1/modules/{$module->name}/enable")
        ->assertConflict()
        ->assertJson([
            'success' => false,
            'error' => 'module_runtime_missing',
        ]);

    $module->refresh();

    expect($module->installed)->toBeFalse()
        ->and($module->enabled)->toBeFalse()
        ->and($module->state)->toBe('failed')
        ->and($module->last_error)->toBe('module_runtime_missing')
        ->and($module->last_failed_at)->not->toBeNull();

    Event::assertNotDispatched(ModuleEnabledEvent::class);
});

function missingRuntimeModule(bool $enabled): Module
{
    return Module::query()->create([
        'name' => 'DefinitelyMissingRuntime',
        'slug' => 'definitely-missing-runtime',
        'version' => '1.0.0',
        'installed' => true,
        'enabled' => $enabled,
        'state' => 'installed',
    ]);
}
