<?php

namespace App\Platform\Storage;

use App\Platform\Storage\Application\FileDiskService;
use App\Platform\Storage\Console\MigrateMediaToPrivateDisk;
use App\Platform\Storage\Contracts\StorageConfigurator;
use App\Platform\Storage\Policies\StorageAccessPolicy;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Spatie\Dropbox\Client as DropboxClient;
use Spatie\FlysystemDropbox\DropboxAdapter;

class StorageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(FileDiskService::class);
        $this->app->bind(
            StorageConfigurator::class,
            fn (Application $app): FileDiskService => $app->make(FileDiskService::class),
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::define('manage backups', [StorageAccessPolicy::class, 'manage']);
        Gate::define('manage file disk', [StorageAccessPolicy::class, 'manage']);

        $this->commands([
            MigrateMediaToPrivateDisk::class,
        ]);

        Storage::extend('dropbox', function ($app, $config) {
            $client = new DropboxClient(
                $config['token']
            );

            $root = trim($config['root'] ?? '', '/');
            $adapter = new DropboxAdapter($client, $root);
            $flysystem = new Filesystem($adapter);

            return new FilesystemAdapter($flysystem, $adapter, $config);
        });
    }
}
