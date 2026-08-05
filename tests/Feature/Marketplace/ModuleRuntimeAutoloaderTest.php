<?php

use App\Services\Marketplace\ModuleRuntimeAutoloader;
use Illuminate\Support\Facades\File;
use Modules\AutoloadProbe\Providers\AutoloadProbeServiceProvider;

afterEach(function () {
    File::deleteDirectory(base_path('Modules/AutoloadProbe'));
});

it('autoloads an installed module before Laravel package providers are registered', function () {
    $modulePath = base_path('Modules/AutoloadProbe');
    File::ensureDirectoryExists($modulePath.'/app/Providers');
    File::put($modulePath.'/module.json', json_encode(['name' => 'AutoloadProbe'], JSON_THROW_ON_ERROR));
    File::put($modulePath.'/app/Providers/AutoloadProbeServiceProvider.php', <<<'PHP'
<?php

namespace Modules\AutoloadProbe\Providers;

class AutoloadProbeServiceProvider {}
PHP);

    ModuleRuntimeAutoloader::registerInstalledModules(base_path('Modules'));

    expect(class_exists(AutoloadProbeServiceProvider::class))->toBeTrue();
});
