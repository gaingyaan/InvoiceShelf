<?php

namespace App\Providers;

use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Persistence\ModelIdentityMap;
use App\Support\Bouncer\BouncerDefaultScope;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Silber\Bouncer\Database\Models as BouncerModels;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/admin/dashboard';

    /**
     * The path to the "customer home" route for your application.
     *
     * This is used by Laravel authentication to redirect customers after login.
     *
     * @var string
     */
    public const CUSTOMER_HOME = '/customer/dashboard';

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ModelIdentityMap::enforce();
        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        if (InstallationState::isDbCreated()) {
            $this->addMenus();
        }

        $this->bootBroadcast();

        // In demo mode, prevent all outgoing emails and notifications
        if (config('app.env') === 'demo') {
            Mail::fake();
            Notification::fake();
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        BouncerModels::scope(new BouncerDefaultScope);
    }

    public function addMenus()
    {
        // main menu
        \Menu::make('main_menu', function ($menu) {
            foreach (config('invoiceshelf.main_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        // admin menu (super admin mode)
        \Menu::make('admin_menu', function ($menu) {
            foreach (config('invoiceshelf.admin_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        // setting menu
        \Menu::make('setting_menu', function ($menu) {
            foreach (config('invoiceshelf.setting_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        \Menu::make('customer_portal_menu', function ($menu) {
            foreach (config('invoiceshelf.customer_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });
    }

    public function generateMenu($menu, $data)
    {
        $menu->add($data['title'], $data['link'])
            ->data('icon', $data['icon'])
            ->data('name', $data['name'])
            ->data('owner_only', $data['owner_only'])
            ->data('super_admin_only', $data['super_admin_only'] ?? false)
            ->data('ability', $data['ability'])
            ->data('model', $data['model'])
            ->data('group', $data['group'])
            ->data('group_label', $data['group_label'] ?? '')
            ->data('priority', $data['priority'] ?? 100);
    }

    public function bootBroadcast()
    {
        Broadcast::routes(['middleware' => 'api.auth']);
    }
}
