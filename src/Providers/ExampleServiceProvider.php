<?php

namespace VendorName\ExamplePackage\Providers;

use Illuminate\Support\ServiceProvider;
use VendorName\ExamplePackage\Console\ExampleCommand;
use VendorName\ExamplePackage\Example;

class ExampleServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('Example', function () {
            return new Example($this->app);
        });

        $this->mergeConfigFrom(
            __DIR__.'/../../config/example.php',
            'example'
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/example.php' => $this->app->configPath('example.php'),
        ], 'config');

        $this->loadViewsFrom(
            __DIR__.'/../../resources/views',
            'Example',
        );

        $this->commands([
            ExampleCommand::class,
        ]);

        $this->app->make('Example');
    }
}
