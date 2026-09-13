<?php

declare(strict_types=1);

namespace Ccharz\DtoLite\Tests;

use Ccharz\DtoLite\LaravelDtoLiteServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Runner\Version;

class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Get package providers.
     *
     * @param  Application  $app
     * @return array<int, class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [
            LaravelDtoLiteServiceProvider::class,
        ];
    }

    /**
     * PHPUnit 13.2 deprecated expectExceptionMessage() in favour of
     * expectExceptionMessageIsOrContains(), but PHPUnit 13 requires PHP 8.4,
     * so the old API stays in use while PHP 8.3 is supported.
     */
    protected function expectExceptionMessageToContain(string $message): void
    {
        if (version_compare(Version::id(), '13.2.0', '>=')) {
            $this->expectExceptionMessageIsOrContains($message);
        } else {
            $this->expectExceptionMessage($message);
        }
    }

    protected function expectExceptionMessageToBe(string $message): void
    {
        if (version_compare(Version::id(), '13.2.0', '>=')) {
            $this->expectExceptionMessageIs($message);
        } else {
            $this->expectExceptionMessage($message);
        }
    }
}
