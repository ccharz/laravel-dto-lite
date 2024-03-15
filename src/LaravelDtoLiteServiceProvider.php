<?php

namespace Ccharz\DtoLite;

use Illuminate\Support\ServiceProvider;

class LaravelDtoLiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->beforeResolving(DataTransferObject::class, function ($class, $parameters, $app) {
            if ($app->has($class)) {
                return;
            }

            $app->bind($class, fn ($container) => $class::make(isset($container['request']) ? $container['request'] : []));
        });
    }
}
