<?php

namespace Roots\AcornExamplePackage\Providers;

use Roots\Acorn\ServiceProvider;
use Roots\AcornExamplePackage\Inspire;

class InspireServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('Inspire', function () {
            return new Inspire($this->app);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/inspire.php' => $this->app->configPath('inspire.php'),
        ], 'config');

        $this->commands([
            \Roots\AcornExamplePackage\Console\InspireCommand::class,
        ]);

        $this->app->make('Inspire');
    }
}
