<?php

declare(strict_types=1);

namespace Brainbo\LaravelAutoTranslation\Services;

use DeepL\DeepLClient;

class DeepLTranslator implements TranslatorInterface
{
    protected string $apiUrl;

    protected string $apiKey;

    protected int $timeout = 10;

    public function __construct(string $apiKey, bool $freeApi = true)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = $freeApi ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate';
    }

    public function translate(array $texts, string $sourceLocale, string $targetLocale): array
    {
        if (empty($texts)) {
            return [];
        }

        $client = new DeepLClient($this->apiKey);
        $result = $client->translateText(
            $texts,
            $this->mapLocaleCode($sourceLocale),
            $this->mapLocaleCode($targetLocale)
        );

        $translations = [];
        foreach ($result as $key => $item) {
            $translations[$texts[$key]] = $item->text;
        }

        return $translations;
    }

    protected function mapLocaleCode(string $locale): string
    {
        $mapping = [
            'en' => 'en-GB',
            'de' => 'de',
            'fr' => 'fr',
            'es' => 'es',
            'pl' => 'pl',
        ];

        $locale = strtolower($locale);
        if (isset($mapping[$locale])) {
            return $mapping[$locale];
        }

        return $locale;
    }
}
