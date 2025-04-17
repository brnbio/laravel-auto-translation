<?php

namespace Brainbo\LaravelAutoTranslation\Tests;

use Brainbo\LaravelAutoTranslation\LaravelAutoTranslationServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelAutoTranslationServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database configuration for testing
        $app['config']->set('database.default', 'testing');

        // Set configuration values for our package
        $app['config']->set('auto-translation.enabled', true);
        $app['config']->set('auto-translation.locales.source', 'en');
        $app['config']->set('auto-translation.locales.target', ['fr', 'de', 'es']);
    }
}
