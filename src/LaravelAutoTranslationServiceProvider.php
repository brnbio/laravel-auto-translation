<?php

namespace BrnBio\LaravelAutoTranslation;

use Illuminate\Support\ServiceProvider;

use BrnBio\LaravelAutoTranslation\Services\DeepLTranslator;
use BrnBio\LaravelAutoTranslation\Services\TranslatorInterface;

class LaravelAutoTranslationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/auto-translation.php' => config_path('auto-translation.php'),
            ], 'config');

            // Registering package commands.
            $this->commands([
                Commands\TranslateCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/auto-translation.php', 'auto-translation');
        
        // Register services that the AutoTranslation class needs
        $this->registerServices();
        
        // Register AutoTranslation singleton
        $this->app->singleton('laravel-auto-translation', function ($app) {
            return new \BrnBio\LaravelAutoTranslation\AutoTranslation();
        });
    }
    
    /**
     * Register translation services
     */
    protected function registerServices()
    {
        // Register the DeepL translator service
        $this->app->bind(TranslatorInterface::class, function ($app) {
            $config = config('auto-translation.service.services.deepl', []);
            $apiKey = $config['api_key'] ?? null;
            
            if (!$apiKey) {
                \Log::warning('DeepL API key not set. Auto-translation will not work.');
                return null;
            }
            
            $freeApi = $config['free_api'] ?? true;
            $timeout = $config['timeout'] ?? 10;
            
            return new DeepLTranslator($apiKey, $freeApi, $timeout);
        });
    }
}