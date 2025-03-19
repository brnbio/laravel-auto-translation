<?php

namespace BrnBio\LaravelAutoTranslation\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepLTranslator implements TranslatorInterface
{
    /**
     * API URL
     * 
     * @var string
     */
    protected $apiUrl;

    /**
     * API Key
     * 
     * @var string
     */
    protected $apiKey;

    /**
     * Request timeout in seconds
     * 
     * @var int
     */
    protected $timeout;

    /**
     * Create a new instance of DeepLTranslator
     *
     * @param string $apiKey
     * @param bool $freeApi
     * @param int $timeout
     */
    public function __construct(string $apiKey, bool $freeApi = true, int $timeout = 10)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = $freeApi 
            ? 'https://api-free.deepl.com/v2/translate' 
            : 'https://api.deepl.com/v2/translate';
        $this->timeout = $timeout;
    }

    /**
     * Translate a batch of text strings
     *
     * @param array $texts
     * @param string $sourceLocale
     * @param string $targetLocale
     * @return array
     */
    public function translateBatch(array $texts, string $sourceLocale, string $targetLocale): array
    {
        if (empty($texts)) {
            return [];
        }

        // Map DeepL language codes
        $sourceLocale = $this->mapLocaleCode($sourceLocale);
        $targetLocale = $this->mapLocaleCode($targetLocale);

        $params = [
            'auth_key' => $this->apiKey,
            'source_lang' => strtoupper($sourceLocale),
            'target_lang' => strtoupper($targetLocale),
            'text' => $texts,
        ];

        try {
            // For debugging
            \Log::debug("Sending DeepL API request", [
                'url' => $this->apiUrl,
                'params' => [
                    'source_lang' => $params['source_lang'],
                    'target_lang' => $params['target_lang'],
                    'text_count' => count($params['text']),
                    'sample_text' => $params['text'][0] ?? ''
                ]
            ]);
            
            // Create a direct request to see the full response for debugging
            $client = new \GuzzleHttp\Client(['timeout' => $this->timeout]);
            $response = $client->request('POST', $this->apiUrl, [
                'form_params' => $params,
                'http_errors' => false
            ]);
            
            // Get the response body
            $body = $response->getBody()->getContents();
            \Log::debug("Raw DeepL API response: " . $body);
            
            // Convert to Laravel HTTP response for compatibility
            $response = Http::response($body, $response->getStatusCode());

            if ($response->successful()) {
                $data = $response->json();
                \Log::debug("DeepL API response", [
                    'status' => $response->status(),
                    'data' => $data
                ]);
                $translations = [];

                // Match translations with original text
                foreach ($data['translations'] as $index => $translation) {
                    $originalText = $texts[$index];
                    $translations[$originalText] = $translation['text'];
                }

                return $translations;
            } else {
                Log::error('DeepL API error: ' . $response->body());
                return [];
            }
        } catch (\Exception $e) {
            Log::error('DeepL translation error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Map Laravel locale codes to DeepL codes
     *
     * @param string $locale
     * @return string
     */
    protected function mapLocaleCode(string $locale): string
    {
        $mapping = [
            'en' => 'EN', // English
            'de' => 'DE', // German
            'fr' => 'FR', // French
            'es' => 'ES', // Spanish
            'it' => 'IT', // Italian
            'nl' => 'NL', // Dutch
            'pl' => 'PL', // Polish
            'pt' => 'PT', // Portuguese
            'pt-br' => 'PT-BR', // Portuguese (Brazil)
            'pt-pt' => 'PT-PT', // Portuguese (Portugal)
            'ru' => 'RU', // Russian
            'ja' => 'JA', // Japanese
            'zh' => 'ZH', // Chinese
            'bg' => 'BG', // Bulgarian
            'cs' => 'CS', // Czech
            'da' => 'DA', // Danish
            'el' => 'EL', // Greek
            'et' => 'ET', // Estonian
            'fi' => 'FI', // Finnish
            'hu' => 'HU', // Hungarian
            'id' => 'ID', // Indonesian
            'lv' => 'LV', // Latvian
            'lt' => 'LT', // Lithuanian
            'ro' => 'RO', // Romanian
            'sk' => 'SK', // Slovak
            'sl' => 'SL', // Slovenian
            'sv' => 'SV', // Swedish
            'tr' => 'TR', // Turkish
            'uk' => 'UK', // Ukrainian
        ];

        // Convert locale to lowercase and handle regional variants (e.g., en-US to en)
        $normalizedLocale = strtolower($locale);
        if (isset($mapping[$normalizedLocale])) {
            return $mapping[$normalizedLocale];
        }

        // Check for general language match when region is specified (e.g., en-US -> en)
        $generalLocale = explode('-', $normalizedLocale)[0];
        if (isset($mapping[$generalLocale])) {
            return $mapping[$generalLocale];
        }

        // If no mapping found, return the original locale code in uppercase
        return strtoupper($locale);
    }
}