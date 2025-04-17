# Laravel Auto Translation

Auto translation package for Laravel applications.

## Installation

You can install the package via composer:

```bash
composer require brnbio/laravel-auto-translation
```

You can publish the config file with:
```bash
php artisan vendor:publish --provider="BrnBio\LaravelAutoTranslation\LaravelAutoTranslationServiceProvider" --tag="config"
```

## Usage

### Scanning for translatable strings

You can scan your Laravel application views for translatable strings and add them to your default language file:

```bash
# Default mode: Scan and only keep translations that are actually used
php artisan translate

# Add mode: Keep all existing translations and add new ones
php artisan translate --add

# Translate to other languages (using DeepL API)
php artisan translate --target=de,fr,es

# Force translation even if target language files already exist
php artisan translate --target=de --force

# With verbose output for debugging
php artisan translate -v
```

By default, this command will:
- Scan all directories specified in the `auto-translation.scan.directories` config (default: `resources/views`)
- Look for file extensions specified in the `auto-translation.scan.extensions` config (default: `.php` and `.blade.php`)
- Extract strings from translation functions like `__()`, `trans()`, `@lang()`, etc.
- Sort all translation keys alphabetically for better organization
- Only keep translations that are actually used in your application
- Add the found strings to the default language file (e.g., `lang/en.json`)

You can customize the directories and extensions in your config file:

```php
'scan' => [
    'directories' => [
        'resources/views',
        'resources/js',
        'app/Http/Controllers',
    ],
    'extensions' => [
        'php', 
        'blade.php',
        'js',
        'vue',
    ],
],
```

Or override them with command options:

```bash
php artisan translate --path=resources/js,resources/vue --extensions=js,vue
```

### Automatic translation with DeepL

The package includes DeepL integration for automatic translation. To use it:

1. Get a DeepL API key from [DeepL API](https://www.deepl.com/pro-api)

2. Add your API key to your `.env` file:
```
DEEPL_API_KEY=your-api-key-here
DEEPL_FREE_API=true # Set to false if using a Pro account
```

3. Run the translate command with target languages:
```bash
# Translate to a single language
php artisan translate --target=de

# Translate to multiple languages
php artisan translate --target=de --target=fr --target=es
```

This will:
- Scan for translations as normal
- Use DeepL API to translate all texts to the target languages
- Create/update the appropriate language files (e.g., `de.json`, `fr.json`, etc.)

### Using the translation API programmatically

```php
use BrnBio\LaravelAutoTranslation\Facades\AutoTranslation;

// Translate text to all target languages
$translations = AutoTranslation::translate('Hello, world!');
// Returns: ['en' => 'Hello, world!', 'de' => 'Hallo, Welt!', 'fr' => 'Bonjour, monde!', ...]

// Translate text to a specific language
$translations = AutoTranslation::translate('Hello, world!', 'en', ['de']);
// Returns: ['en' => 'Hello, world!', 'de' => 'Hallo, Welt!']

// Translate multiple texts at once
$texts = ['Hello', 'Goodbye', 'Thank you'];
$translations = AutoTranslation::translateBatch($texts, 'fr', 'en');
// Returns: ['Hello' => 'Bonjour', 'Goodbye' => 'Au revoir', 'Thank you' => 'Merci']
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
