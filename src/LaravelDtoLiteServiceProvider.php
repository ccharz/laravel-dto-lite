<?php

namespace Ccharz\DtoLite;

use Illuminate\Support\ServiceProvider;

class LaravelDtoLiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->beforeResolving(DataTransferObject::class, function ($class, $parameters, $app): void {
            if ($app->has($class)) {
                return;
            }

            $app->bind($class, fn ($container) => $class::make($container['request'] ?? []));
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateDataTransferObjectCommand::class,
            ]);
        }
    }
}
