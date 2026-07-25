<?php

namespace Sandip\SecureStorage\Native;

use Illuminate\Support\ServiceProvider;
use Sandip\SecureStorage\Native\Commands\CopyAssetsCommand;

class SecureStorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SecureStorage::class, fn () => new SecureStorage);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CopyAssetsCommand::class,
            ]);
        }
    }
}
