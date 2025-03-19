<?php

namespace BrnBio\LaravelAutoTranslation\Commands;

use BrnBio\LaravelAutoTranslation\AutoTranslation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TranslateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translate 
                            {--path= : Override the path(s) to scan for translations}
                            {--extensions= : Override the file extensions to scan}
                            {--add : Add new translations to existing ones instead of only keeping used translations}
                            {--target=* : Target language(s) to translate to (e.g. de,fr,es)}
                            {--force : Force translation even if the target language file already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan all views for translated strings and add them to the default language JSON file';

    /**
     * Regular expressions to match translation strings.
     *
     * @var array
     */
    protected $patterns = [
        // Pattern for Laravel translation functions in PHP
        '/__\(\s*[\'"](.+?)[\'"]\s*[\),]/',
        '/trans\(\s*[\'"](.+?)[\'"]\s*[\),]/',
        '/trans_choice\(\s*[\'"](.+?)[\'"]\s*,/',
        
        // Pattern for Blade template translation calls
        '/@lang\(\s*[\'"](.+?)[\'"]\s*\)/',
        '/\{\{\s*__\(\s*[\'"](.+?)[\'"]\s*\)\s*\}\}/',
        '/\{\{__\(\s*[\'"](.+?)[\'"]\s*\)\}\}/',
        '/\{\{\s*trans\(\s*[\'"](.+?)[\'"]\s*\)\s*\}\}/',
        '/\{\{trans\(\s*[\'"](.+?)[\'"]\s*\)\}\}/',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Scanning for translations...');
        
        $sourceLocale = config('auto-translation.locales.source', 'en');
        
        // Get scan directories from config, or use command option if provided
        $scanDirectories = config('auto-translation.scan.directories', ['resources/views']);
        if ($this->option('path')) {
            $scanDirectories = explode(',', $this->option('path'));
        }
        
        // Get extensions from config, or use command option if provided
        $extensions = config('auto-translation.scan.extensions', ['php', 'blade.php']);
        if ($this->option('extensions')) {
            $extensions = explode(',', $this->option('extensions'));
        }
        
        // Ensure the language directory exists
        $langPath = lang_path();
        if (!File::isDirectory($langPath)) {
            File::makeDirectory($langPath, 0755, true);
        }
        
        // Get the JSON file path for the source locale
        $jsonFilePath = lang_path("$sourceLocale.json");
        
        // Find all files with the specified extensions in all directories
        $files = $this->getAllFiles($scanDirectories, $extensions);
        
        $fileCount = count($files);
        $this->info("Scanning $fileCount files...");
        
        if ($fileCount === 0) {
            $this->warn("No files found to scan in the configured directories.");
            return Command::SUCCESS;
        }
        
        // Extract translations from files
        $usedTranslations = $this->extractTranslations($files);
        $this->info("Found " . count($usedTranslations) . " unique translation strings.");
        
        // Sort translation keys alphabetically
        sort($usedTranslations);
        
        // Load existing translations if the file exists, to preserve any manual translations
        $existingTranslations = [];
        if (File::exists($jsonFilePath)) {
            $existingTranslations = json_decode(File::get($jsonFilePath), true) ?? [];
        }
        
        // Merge translations based on the mode (add or replace)
        $finalTranslations = [];
        
        if ($this->option('add')) {
            // Add mode: Keep all existing translations and add new ones
            $finalTranslations = $existingTranslations;
            
            foreach ($usedTranslations as $key) {
                if (!isset($finalTranslations[$key])) {
                    $finalTranslations[$key] = $key;
                }
            }
            
            $this->info("Mode: Adding new translations to existing ones");
        } else {
            // Replace mode: Only keep translations that are actually used
            foreach ($usedTranslations as $key) {
                $finalTranslations[$key] = $existingTranslations[$key] ?? $key;
            }
            
            $this->info("Mode: Only keeping translations that are actually used");
        }
        
        // Sort final translations alphabetically by key
        ksort($finalTranslations);
        
        // Save the translations file
        File::put($jsonFilePath, json_encode($finalTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->info("Updated language file $jsonFilePath with " . count($finalTranslations) . " translations.");
        
        // Check if we need to translate to other languages
        $targetLocales = $this->option('target');
        if (!empty($targetLocales)) {
            // Get the DeepL API key from the environment for direct access
            $apiKey = env('DEEPL_API_KEY');
            if (empty($apiKey)) {
                $this->error("Translation error: DeepL API key is not set.");
                $this->info("Please set your DeepL API key in your .env file:");
                $this->info("DEEPL_API_KEY=your-api-key-here");
                return Command::FAILURE;
            }
            
            $this->translateToTargetLanguages($finalTranslations, $sourceLocale, $targetLocales);
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Extract all translation keys from the given files.
     *
     * @param array $files
     * @return array
     */
    protected function extractTranslations(array $files): array
    {
        $bar = $this->output->createProgressBar(count($files));
        $bar->start();
        
        $translations = [];
        
        foreach ($files as $file) {
            $content = File::get($file);
            
            // Only show filename in verbose mode
            if ($this->getOutput()->isVerbose()) {
                $this->comment("Processing file: $file");
            }
            
            foreach ($this->patterns as $pattern) {
                preg_match_all($pattern, $content, $matches);
                
                // Get all non-empty captures
                $allMatches = array_filter(array_merge($matches[1] ?? [], $matches[2] ?? []), function($value) {
                    return !empty($value);
                });
                
                if (!empty($allMatches)) {
                    foreach ($allMatches as $match) {
                        // Skip if the match is a path (contains dots) and doesn't contain spaces
                        if (Str::contains($match, '.') && !Str::contains($match, ' ')) {
                            continue;
                        }
                        
                        // Add to translations if not already present
                        if (!isset($translations[$match])) {
                            $translations[$match] = true; // Just using as a set
                        }
                    }
                }
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        return array_keys($translations);
    }
    
    /**
     * Translate to target languages.
     *
     * @param array $translations
     * @param string $sourceLocale
     * @param array $targetLocales
     * @return void
     */
    protected function translateToTargetLanguages(array $translations, string $sourceLocale, array $targetLocales): void
    {
        // Get DeepL API settings directly from environment
        $apiKey = env('DEEPL_API_KEY');
        $freeApi = env('DEEPL_FREE_API', true);
        $timeout = env('DEEPL_TIMEOUT', 10);
        
        $translator = new \BrnBio\LaravelAutoTranslation\Services\DeepLTranslator($apiKey, $freeApi, $timeout);
        $this->info("Using DeepL translator directly");
        $langPath = lang_path();
        $force = $this->option('force');
        
        // Get list of strings to translate
        $textsToTranslate = array_keys($translations);
        $totalTexts = count($textsToTranslate);
        
        $this->info("Translating $totalTexts strings to " . count($targetLocales) . " languages...");
        
        foreach ($targetLocales as $targetLocale) {
            $targetFilePath = lang_path("$targetLocale.json");
            
            // Check if target file exists and we're not forcing translation
            if (File::exists($targetFilePath) && !$force) {
                $this->warn("Target language file $targetFilePath already exists. Use --force to overwrite it.");
                continue;
            }
            
            $this->info("Translating to $targetLocale...");
            
            // Load existing translations if any
            $existingTranslations = [];
            if (File::exists($targetFilePath)) {
                $existingTranslations = json_decode(File::get($targetFilePath), true) ?? [];
            }
            
            // Process translations in batches
            $this->info("Starting direct translation with DeepL");
            
            $translatedTexts = [];
            $totalTexts = count($textsToTranslate);
            
            $this->info("Translating $totalTexts strings from $sourceLocale to $targetLocale...");
            
            // Show progress for all texts
            $bar = $this->output->createProgressBar($totalTexts);
            $bar->start();
            
            // Process all texts
            $textsToProcess = $textsToTranslate;
            
            // Process in batches of 50 for efficiency (DeepL allows up to 50 per request)
            $batchSize = 50;
            $batches = array_chunk($textsToProcess, $batchSize);
            
            foreach ($batches as $batch) {
                try {
                    // Create a direct DeepL API call using cURL
                    $apiUrl = $freeApi ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate';
                    
                    // DeepL requires 'text' to be passed as multiple parameters with the same name
                    $queryParams = [];
                    $queryParams['auth_key'] = $apiKey;
                    $queryParams['source_lang'] = strtoupper($sourceLocale);
                    $queryParams['target_lang'] = strtoupper($targetLocale);
                    
                    // Add each text as a separate text parameter
                    foreach ($batch as $text) {
                        $queryParams['text'][] = $text;
                    }
                    
                    $curl = curl_init();
                    // Properly build query for multiple text parameters
                    $postFields = [];
                    foreach ($queryParams as $key => $value) {
                        if (is_array($value)) {
                            // Handle array values (like multiple 'text' parameters)
                            foreach ($value as $item) {
                                $postFields[] = urlencode($key) . '=' . urlencode($item);
                            }
                        } else {
                            $postFields[] = urlencode($key) . '=' . urlencode($value);
                        }
                    }
                    $postString = implode('&', $postFields);
                    
                    curl_setopt_array($curl, [
                        CURLOPT_URL => $apiUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $postString,
                        CURLOPT_TIMEOUT => $timeout
                    ]);
                    
                    $response = curl_exec($curl);
                    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                    
                    if ($httpCode === 200) {
                        $data = json_decode($response, true);
                        
                        if (isset($data['translations']) && is_array($data['translations'])) {
                            // Map response back to original texts quietly
                            foreach ($batch as $index => $originalText) {
                                if (isset($data['translations'][$index]['text'])) {
                                    $translatedText = $data['translations'][$index]['text'];
                                    $translatedTexts[$originalText] = $translatedText;
                                }
                            }
                        } else {
                            $this->error("Invalid response format: " . $response);
                        }
                    } else {
                        $this->error("API Error: " . $response);
                    }
                    
                    curl_close($curl);
                } catch (\Exception $e) {
                    $this->error("Translation error: " . $e->getMessage());
                }
                
                // Advance progress bar for each text in the batch
                for ($i = 0; $i < count($batch); $i++) {
                    $bar->advance();
                }
                
                // Sleep a little to avoid rate limits on free API
                usleep(100000); // 100ms
            }
            
            $bar->finish();
            $this->newLine();
            
            // Check if we actually got any translations
            if (empty($translatedTexts)) {
                $this->error("No translations received from DeepL API.");
                continue;
            }
            
            // Create the translation file structure - keys are original text, values are translations
            $targetTranslations = [];
            
            foreach ($textsToTranslate as $text) {
                // Get the translation for this text
                if (isset($translatedTexts[$text])) {
                    // We have a translation - use it
                    $targetTranslations[$text] = $translatedTexts[$text];
                } else if (isset($existingTranslations[$text])) {
                    // Fall back to existing translation if available
                    $targetTranslations[$text] = $existingTranslations[$text];
                } else {
                    // Last resort - use original text
                    $targetTranslations[$text] = $text;
                }
            }
            
            // Sort and save
            ksort($targetTranslations);
            File::put($targetFilePath, json_encode($targetTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $this->info("Successfully translated to $targetLocale. Created $targetFilePath with " . count($targetTranslations) . " translations.");
        }
    }

    /**
     * Get all files to scan for translations.
     *
     * @param array $scanDirectories
     * @param array $extensions
     * @return array
     */
    protected function getAllFiles(array $scanDirectories, array $extensions): array
    {
        $files = [];
        
        // Debug directory paths only in verbose mode
        if ($this->getOutput()->isVerbose()) {
            $this->info("Base path: " . base_path());
        }
        
        foreach ($scanDirectories as $directory) {
            $directoryPath = base_path(trim($directory));
            
            if ($this->getOutput()->isVerbose()) {
                $this->info("Scanning directory: " . $directoryPath);
            }
            
            if (!File::isDirectory($directoryPath)) {
                $this->warn("Directory does not exist: " . $directoryPath);
                continue;
            }
            
            $directoryFiles = $this->findFiles($directoryPath, $extensions);
            $files = array_merge($files, $directoryFiles);
        }
        
        return $files;
    }

    /**
     * Find all files with the specified extensions in the given path.
     *
     * @param string $path
     * @param array $extensions
     * @return array
     */
    protected function findFiles($path, $extensions)
    {
        $files = [];
        
        // Output debug info only in verbose mode
        if ($this->getOutput()->isVerbose()) {
            $this->line("Detailed scan of directory: $path");
        }
        
        // Use direct file scanning with RecursiveDirectoryIterator
        foreach ($extensions as $extension) {
            $extension = trim($extension);
            if ($this->getOutput()->isVerbose()) {
                $this->line("Looking for *.$extension files...");
            }
            
            try {
                $extensionFiles = [];
                $directoryIterator = new \RecursiveDirectoryIterator(
                    $path, 
                    \RecursiveDirectoryIterator::SKIP_DOTS
                );
                $iterator = new \RecursiveIteratorIterator(
                    $directoryIterator,
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        // Check for blade.php extension specially
                        $filepath = $file->getPathname();
                        if ($extension === 'blade.php' && str_ends_with($filepath, '.blade.php')) {
                            $extensionFiles[] = $filepath;
                            if ($this->getOutput()->isVeryVerbose()) {
                                $this->line("Added blade file: " . $filepath);
                            }
                        } 
                        // Check regular extensions
                        else if ($file->getExtension() === $extension) {
                            $extensionFiles[] = $filepath;
                            if ($this->getOutput()->isVeryVerbose()) {
                                $this->line("Added file: " . $filepath);
                            }
                        }
                    }
                }
                
                if (count($extensionFiles) > 0) {
                    if ($this->getOutput()->isVerbose()) {
                        $this->line("Found " . count($extensionFiles) . " files with .$extension extension");
                    }
                    $files = array_merge($files, $extensionFiles);
                } else if ($this->getOutput()->isVerbose()) {
                    $this->line("No files found with .$extension extension");
                }
            } catch (\Exception $e) {
                $this->error("Error scanning for $extension files: " . $e->getMessage());
            }
        }
        
        return $files;
    }
}