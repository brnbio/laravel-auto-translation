<?php

namespace BrnBio\LaravelAutoTranslation\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \BrnBio\LaravelAutoTranslation\AutoTranslation
 */
class AutoTranslation extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-auto-translation';
    }
}