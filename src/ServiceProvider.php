<?php

namespace Nicklayb\LaravelDbImport;

use Nicklayb\LaravelDbImport\DbImport;
use Illuminate\Support\ServiceProvider as BaseProvider;

class ServiceProvider extends BaseProvider
{
    /**
     * Config file name
     *
     * @var string
     */
    const CONFIG_FILE = 'dbimport.php';

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/'.static::CONFIG_FILE => config_path(static::CONFIG_FILE),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DbImport::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'Nicklayb\LaravelDbImport\ImportCommand',
            'Nicklayb\LaravelDbImport\Import'
        ];
    }
}
