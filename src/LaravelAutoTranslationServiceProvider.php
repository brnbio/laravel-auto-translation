<?php

declare(strict_types=1);

namespace Brainbo\LaravelAutoTranslation;

use Illuminate\Support\ServiceProvider;

class LaravelAutoTranslationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes(
            paths: [__DIR__ . '/../config/auto-translation.php' => config_path('auto-translation.php')],
            groups: 'config'
        );

        $this->commands([
            Commands\TranslateCommand::class,
        ]);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/auto-translation.php', 'auto-translation');
        $this->app->singleton('laravel-auto-translation', fn() => new AutoTranslation());
    }
}
