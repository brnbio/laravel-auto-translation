<?php

declare(strict_types=1);

namespace Brainbo\LaravelAutoTranslation\Services;

interface TranslatorInterface
{
    public function translate(array $texts, string $sourceLocale, string $targetLocale): array;
}
