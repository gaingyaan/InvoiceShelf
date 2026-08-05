<?php

namespace App\Platform\Modules\Runtime;

use App\Platform\Modules\Events\ModuleEnabledEvent;
use App\Platform\Modules\Events\ModuleInstalledEvent;
use App\Platform\Modules\Models\Module as ModelsModule;
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
