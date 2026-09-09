<?php

declare(strict_types=1);

namespace Ccharz\DtoLite;

use Ccharz\DtoLite\Contracts\DataTransferObject;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class LaravelDtoLiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->beforeResolving(
            DataTransferObject::class,
            /**
             * @param  class-string<DataTransferObject>  $class
             * @param  array<string, mixed>  $parameters
             */
            function ($class, $parameters, $app): void {
                if ($app->has($class)) {
                    return;
                }

                $app->bind($class, fn (Application $container) => $class::make($container->request ?? []));
            }
        );
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
