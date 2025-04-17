<?php

declare(strict_types=1);

namespace Brainbo\LaravelAutoTranslation\Commands;

use Brainbo\LaravelAutoTranslation\Services\DeepLTranslator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

class TranslateCommand extends Command
{
    protected $signature = 'translate';

    protected $description = 'Scan all views for translated strings and add them to the default language JSON file';

    protected array $patterns = [
        '/__\(\s*[\'"](.+?)[\'"]\s*[\),]/',
        '/trans\(\s*[\'"](.+?)[\'"]\s*[\),]/',
        '/trans_choice\(\s*[\'"](.+?)[\'"]\s*,/',
        '/@lang\(\s*[\'"](.+?)[\'"]\s*\)/',
        '/\{\{\s*__\(\s*[\'"](.+?)[\'"]\s*\)\s*\}\}/',
        '/\{\{__\(\s*[\'"](.+?)[\'"]\s*\)\}\}/',
        '/\{\{\s*trans\(\s*[\'"](.+?)[\'"]\s*\)\s*\}\}/',
        '/\{\{trans\(\s*[\'"](.+?)[\'"]\s*\)\}\}/',
    ];

    public function handle(): int
    {
        $locale = config('auto-translation.locales.source');

        $keys = $this->getMessages();
        $this->info('Write ' . $locale . '.json');
        File::put(lang_path($locale . '.json'), json_encode(array_combine($keys, $keys), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        foreach (config('auto-translation.locales.target', []) as $targetLocale) {
            $this->info('Write ' . $targetLocale . '.json');
            $messages = $this->translateToTargetLocale($targetLocale, $keys);
            File::put(lang_path($targetLocale . '.json'), json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        $this->info('Done!');

        return Command::SUCCESS;
    }

    protected function getMessages(): array
    {
        $this->info('Scan directories for files');
        $directories = config('auto-translation.scan.directories', ['resources/views']);
        $bar = $this->output->createProgressBar(count($directories));
        $bar->start();
        $files = [];
        foreach ($directories as $directory) {
            // todo: extensions
            $files = array_merge($files, File::allFiles(base_path($directory)));
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->info('Scan files');
        $bar = $this->output->createProgressBar(count($files));
        $bar->start();
        $messages = [];
        foreach ($files as $file) {
            $messages = array_merge($messages, $this->extractMessages($file));
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        sort($messages);

        return array_unique($messages);
    }

    protected function extractMessages(SplFileInfo $file): array
    {
        $messages = [];
        $content = $file->getContents();
        foreach ($this->patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            $allMatches = array_filter(array_merge($matches[1] ?? [], $matches[2] ?? []), fn($value) => !empty($value));
            foreach ($allMatches as $item) {
                $messages[] = $item;
            }
        }

        return $messages;
    }

    protected function translateToTargetLocale(string $targetLocale, array $keys): array
    {
        $translations = [];
        $langPath = lang_path($targetLocale . '.json');
        if (file_exists($langPath)) {
            $translations = json_decode(file_get_contents($langPath), true);
        }
        $toTranslate = array_values(array_diff($keys, array_keys($translations)));
        $translations = array_merge($translations, $this->translate($targetLocale, $toTranslate));
        ksort($translations);

        return $translations;
    }

    protected function translate(string $locale, array $keys): array
    {
        $sourceLocale = config('auto-translation.locales.source');

        $translator = app(DeepLTranslator::class, [
            'apiKey' => config('auto-translation.service.services.deepl.api_key'),
        ]);

        return $translator->translate($keys, $sourceLocale, $locale);
    }
}
