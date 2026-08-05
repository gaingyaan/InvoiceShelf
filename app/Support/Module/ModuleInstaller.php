<?php

namespace App\Support\Module;

use App\Events\ModuleEnabledEvent;
use App\Events\ModuleInstalledEvent;
use App\Models\Module as ModelsModule;
use Artisan;
use Nwidart\Modules\Facades\Module;

class ModuleInstaller
{
    public static function complete($module, $version): bool
    {
        Module::register();

        Artisan::call("module:migrate $module --force");
        Artisan::call("module:enable $module");

        $module = ModelsModule::updateOrCreate(
            ['name' => $module],
            ['version' => $version, 'installed' => true, 'enabled' => true]
        );

        ModuleInstalledEvent::dispatch($module);
        ModuleEnabledEvent::dispatch($module);

        return true;
    }
}
