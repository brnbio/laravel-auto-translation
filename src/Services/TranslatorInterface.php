<?php

namespace BrnBio\LaravelAutoTranslation\Services;

interface TranslatorInterface
{
    /**
     * Translate a batch of text strings
     *
     * @param array $texts
     * @param string $sourceLocale
     * @param string $targetLocale
     * @return array
     */
    public function translateBatch(array $texts, string $sourceLocale, string $targetLocale): array;
}