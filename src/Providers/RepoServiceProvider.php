<?php
namespace MrNewport\LaravelRepo\Providers;

use Illuminate\Support\ServiceProvider;
use MrNewport\LaravelRepo\Services\RepoService;

class RepoServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(RepoService::class, function($app) {
            return new RepoService();

        });
        $this->mergeConfigFrom(
            __DIR__.'/../../config/repo.php', 'repo'
        );
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/repo.php' => config_path('repo.php'),
        ], 'config');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \MrNewport\LaravelRepo\Commands\FetchRepos::class,
            ]);
        }
    }
}
