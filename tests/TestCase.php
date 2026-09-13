<?php

namespace MrNewport\LaravelRepo\Tests;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [\MrNewport\LaravelRepo\Providers\RepoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('repo.github_username', 'example');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate')->run();
    }
}
