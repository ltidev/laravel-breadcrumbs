<?php

namespace BabDev\Breadcrumbs\Providers;

use BabDev\Breadcrumbs\BreadcrumbsGenerator;
use BabDev\Breadcrumbs\BreadcrumbsManager;
use BabDev\Breadcrumbs\Contracts\BreadcrumbsGenerator as BreadcrumbsGeneratorContract;
use BabDev\Breadcrumbs\Contracts\BreadcrumbsManager as BreadcrumbsManagerContract;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class BreadcrumbsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            'breadcrumbs.generator',
            BreadcrumbsGeneratorContract::class,
            BreadcrumbsGenerator::class,

            'breadcrumbs.manager',
            BreadcrumbsManagerContract::class,
            BreadcrumbsManager::class,
        ];
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views/', 'breadcrumbs');

        $this->publishes(
            [
                __DIR__ . '/../../config/breadcrumbs.php' => config_path('breadcrumbs.php'),
            ],
            'config'
        );

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [
                    __DIR__ . '/resources/views' => resource_path('views/vendor/pagination'),
                ],
                'laravel-breadcrumbs'
            );
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/breadcrumbs.php', 'breadcrumbs');

        // Register services
        $this->registerGenerator();
        $this->registerManager();
    }

    /**
     * Registers the binding for the breadcrumbs generator.
     *
     * @return void
     */
    private function registerGenerator(): void
    {
        $this->app->bind(
            'breadcrumbs.generator',
            static function (Application $app): BreadcrumbsGeneratorContract {
                return new BreadcrumbsGenerator();
            }
        );

        $this->app->alias('breadcrumbs.generator', BreadcrumbsGeneratorContract::class);
        $this->app->alias('breadcrumbs.generator', BreadcrumbsGenerator::class);
    }

    /**
     * Registers the binding for the breadcrumbs manager.
     *
     * @return void
     */
    private function registerManager(): void
    {
        $this->app->singleton(
            'breadcrumbs.manager',
            static function (Application $app): BreadcrumbsManagerContract {
                return new BreadcrumbsManager(
                    $app->make('breadcrumbs.generator'),
                    $app->make('router'),
                    $app->make('view')
                );
            }
        );

        $this->callAfterResolving(
            'breadcrumbs.manager',
            function (BreadcrumbsManagerContract $manager, Application $app): void {
                $this->registerBreadcrumbs($manager, $app);
            }
        );

        $this->app->alias('breadcrumbs.manager', BreadcrumbsManagerContract::class);
        $this->app->alias('breadcrumbs.manager', BreadcrumbsManager::class);
    }

    /**
     * Load the routes/breadcrumbs.php file (if it exists) which registers available breadcrumbs.
     *
     * @return void
     */
    protected function registerBreadcrumbs(BreadcrumbsManagerContract $breadcrumbs, Application $app): void
    {
        /** @var Repository $config */
        $config = $app->make('config');

        // Load the routes/breadcrumbs.php file, or other configured file(s)
        $files = $config->get('breadcrumbs.files');

        if (!$files) {
            return;
        }

        // If it is set to the default value and that file doesn't exist, skip loading it rather than causing an error
        if ($files === base_path('routes/breadcrumbs.php') && ! \is_file($files)) {
            return;
        }

        // Support both a single string filename and an array of filenames (e.g. returned by glob())
        foreach ((array) $files as $file) {
            require $file;
        }
    }
}
