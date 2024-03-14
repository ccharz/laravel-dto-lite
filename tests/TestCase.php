<?php

namespace Ccharz\DtoLite\Tests;

use Ccharz\DtoLite\LaravelDtoLiteServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [
            LaravelDtoLiteServiceProvider::class,
        ];
    }
}