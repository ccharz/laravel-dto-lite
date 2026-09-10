<?php

declare(strict_types=1);

namespace Ccharz\DtoLite;

use Ccharz\DtoLite\Contracts\DataTransferObject;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

class LaravelDtoLiteServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->beforeResolving(
            DataTransferObject::class,
            /**
             * @param  class-string<DataTransferObject>  $class
             * @param  array<string, mixed>  $parameters
             */
            function (string $class, array $parameters, Application $app): void {
                if (! $app->has($class)) {
                    $app->bind(
                        $class,
                        /**
                         * @return DataTransferObject
                         */
                        function (Application $container) use ($class): DataTransferObject {
                            $request_data = $container->bound('request')
                                ? $container->make('request')
                                : [];

                            return $class::make($request_data);
                        }
                    );
                }
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
