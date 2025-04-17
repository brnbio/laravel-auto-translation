<?php

namespace Brainbo\LaravelAutoTranslation;

use Brainbo\LaravelAutoTranslation\Services\DeepLTranslator;
use Brainbo\LaravelAutoTranslation\Services\TranslatorInterface;
use Illuminate\Support\Facades\Log;

class AutoTranslation
{
    /**
     * The translator service to use
     *
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * Source locale
     *
     * @var string
     */
    protected $sourceLocale;

    /**
     * Target locales
     *
     * @var array
     */
    protected $targetLocales;

    /**
     * Create a new AutoTranslation instance.
     *
     * @param TranslatorInterface|null $translator
     */
    public function __construct(?TranslatorInterface $translator = null)
    {
        $this->sourceLocale = config('auto-translation.locales.source', 'en');
        $this->targetLocales = config('auto-translation.locales.target', ['de', 'fr', 'es']);
        $this->setupTranslator($translator);
    }

    /**
     * Set up the translator service based on configuration
     *
     * @param TranslatorInterface|null $translator
     * @return void
     */
    protected function setupTranslator(?TranslatorInterface $translator = null): void
    {
        if ($translator) {
            $this->translator = $translator;
            return;
        }

        // Try to get the TranslatorInterface from the service container
        try {
            $this->translator = app(TranslatorInterface::class);

            // Log successful initialization
            if ($this->translator) {
                Log::debug('Translator service initialized:', [
                    'class' => get_class($this->translator)
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to initialize translator service:', [
                'error' => $e->getMessage()
            ]);
            $this->translator = null;
        }
    }

    /**
     * Translate a string to all target languages.
     *
     * @param string $text The text to translate
     * @param string|null $sourceLocale Source locale (defaults to config value)
     * @param array|null $targetLocales Target locales (defaults to config value)
     * @return array Array of translations with locale codes as keys
     */
    public function translate(string $text, ?string $sourceLocale = null, ?array $targetLocales = null): array
    {
        if (!$this->translator) {
            return [$text];
        }

        $sourceLocale = $sourceLocale ?? $this->sourceLocale;
        $targetLocales = $targetLocales ?? $this->targetLocales;

        $result = [$sourceLocale => $text];

        foreach ($targetLocales as $targetLocale) {
            if ($targetLocale === $sourceLocale) {
                continue;
            }

            $translations = $this->translator->translateBatch([$text], $sourceLocale, $targetLocale);

            if (!empty($translations)) {
                $result[$targetLocale] = $translations[$text];
            }
        }

        return $result;
    }

    /**
     * Translate an array of strings to a target language.
     *
     * @param array $texts Array of texts to translate
     * @param string $targetLocale Target locale
     * @param string|null $sourceLocale Source locale (defaults to config value)
     * @return array Associative array with original texts as keys and translations as values
     */
    public function translateBatch(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        if (!$this->translator || empty($texts)) {
            return array_combine($texts, $texts);
        }

        $sourceLocale = $sourceLocale ?? $this->sourceLocale;

        if ($targetLocale === $sourceLocale) {
            return array_combine($texts, $texts);
        }

        try {
            // For debugging
            \Log::debug("Translating " . count($texts) . " texts from $sourceLocale to $targetLocale", [
                'first_texts' => array_slice($texts, 0, 3),
                'translator_class' => get_class($this->translator)
            ]);

            $translations = $this->translator->translateBatch($texts, $sourceLocale, $targetLocale);

            // Log result
            \Log::debug("Received " . count($translations) . " translations", [
                'sample' => !empty($translations) ? array_slice($translations, 0, 1, true) : []
            ]);
        } catch (\Exception $e) {
            \Log::error("Translation error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return array_combine($texts, $texts);
        }

        // Fill in any missing translations with the original text
        foreach ($texts as $text) {
            if (!isset($translations[$text])) {
                $translations[$text] = $text;
            }
        }

        return $translations;
    }
}
