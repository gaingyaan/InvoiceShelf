<?php

namespace App\Platform\Modules;

use App\Platform\Modules\Console\InstallModuleCommand;
use App\Platform\Modules\Console\UninstallModuleCommand;
use App\Platform\Modules\Contracts\ModuleSettingsStore;
use App\Platform\Modules\Infrastructure\EloquentModuleSettingsStore;
use App\Platform\Modules\Policies\ModulePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ModuleSettingsStore::class, EloquentModuleSettingsStore::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallModuleCommand::class,
                UninstallModuleCommand::class,
            ]);
        }

        Gate::define('manage modules', [ModulePolicy::class, 'manageModules']);
        Gate::define('manage module settings', [ModulePolicy::class, 'manageSettings']);
    }
}
