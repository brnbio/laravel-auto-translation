<?php

namespace BrnBio\LaravelAutoTranslation\Tests\Commands;

use BrnBio\LaravelAutoTranslation\Tests\TestCase;
use Illuminate\Support\Facades\File;

class TranslateCommandTest extends TestCase
{
    protected $testViewsPath;
    protected $langPath;
    protected $jsonFilePath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test views directory
        $this->testViewsPath = base_path('tests/views');
        File::makeDirectory($this->testViewsPath, 0755, true, true);
        
        // Set up language path
        $this->langPath = lang_path();
        if (!File::isDirectory($this->langPath)) {
            File::makeDirectory($this->langPath, 0755, true);
        }
        
        $this->jsonFilePath = lang_path('en.json');
        
        // Override config for testing
        config(['auto-translation.scan.directories' => ['tests/views']]);
        
        // Create a test view file with translations
        $this->createTestViewFile();
    }

    protected function tearDown(): void
    {
        // Clean up test directories
        File::deleteDirectory($this->testViewsPath);
        
        // Delete test translation file
        if (File::exists($this->jsonFilePath)) {
            File::delete($this->jsonFilePath);
        }
        
        parent::tearDown();
    }

    /** @test */
    public function it_can_scan_and_extract_translations_and_sort_them_alphabetically()
    {
        $this->artisan('translate')
        ->expectsOutput('Scanning for translations...')
        ->expectsOutput('Mode: Only keeping translations that are actually used')
        ->assertSuccessful();
        
        $this->assertTrue(File::exists($this->jsonFilePath));
        
        $translations = json_decode(File::get($this->jsonFilePath), true);
        
        $this->assertIsArray($translations);
        $this->assertArrayHasKey('Hello World', $translations);
        $this->assertArrayHasKey('No Spaces', $translations);
        $this->assertArrayHasKey('Welcome to our application', $translations);
        $this->assertArrayHasKey('No Spaces Trans', $translations);
        $this->assertArrayHasKey('This is a test', $translations);
        
        // Check that it captures variables
        $this->assertArrayHasKey('This has a $variable', $translations);
        $this->assertArrayHasKey('This has a :placeholder', $translations);
        
        // Verify the keys are sorted alphabetically
        $keys = array_keys($translations);
        $sortedKeys = $keys;
        sort($sortedKeys);
        $this->assertEquals($sortedKeys, $keys, "Keys should be sorted alphabetically");
        
        // Make sure it doesn't extract translation keys with dots (usually these are from translation files)
        $this->assertArrayNotHasKey('auth.failed', $translations);
    }

    /** @test */
    public function it_only_keeps_used_translations_in_default_mode()
    {
        // Create an initial translations file with extra translations
        $initialTranslations = [
            'Existing translation' => 'Existing translation',
            'Unused translation' => 'This should be removed',
            'Hello World' => 'Custom Hello World Value'
        ];
        
        File::put($this->jsonFilePath, json_encode($initialTranslations, JSON_PRETTY_PRINT));
        
        $this->artisan('translate')
            ->expectsOutput('Mode: Only keeping translations that are actually used')
            ->assertSuccessful();
        
        $translations = json_decode(File::get($this->jsonFilePath), true);
        
        // Should remove unused translations
        $this->assertArrayNotHasKey('Existing translation', $translations);
        $this->assertArrayNotHasKey('Unused translation', $translations);
        
        // Should keep used translations and preserve their values
        $this->assertArrayHasKey('Hello World', $translations);
        $this->assertEquals('Custom Hello World Value', $translations['Hello World']);
    }
    
    /** @test */
    public function it_overrides_directories_when_path_option_is_provided()
    {
        // Create a different test directory
        $customPath = base_path('tests/custom');
        File::makeDirectory($customPath, 0755, true, true);
        
        // Create a custom test file with different translations
        $content = <<<'EOT'
<h1>{{ __('Custom Title') }}</h1>
<p>{{ trans('Custom Text') }}</p>
EOT;
        File::put($customPath . '/custom.blade.php', $content);
        
        // Run with custom path
        $this->artisan('translate', [
            '--path' => 'tests/custom'
        ])->assertSuccessful();
        
        $translations = json_decode(File::get($this->jsonFilePath), true);
        
        // Should find custom translations
        $this->assertArrayHasKey('Custom Title', $translations);
        $this->assertArrayHasKey('Custom Text', $translations);
        
        // Should not find translations from the config path
        $this->assertArrayNotHasKey('Hello World', $translations);
        
        // Cleanup
        File::deleteDirectory($customPath);
    }
    
    /** @test */
    public function it_adds_new_translations_when_using_add_flag()
    {
        // Create an initial translations file
        $initialTranslations = [
            'Existing translation' => 'Existing translation',
            'Another one' => 'Another one'
        ];
        
        File::put($this->jsonFilePath, json_encode($initialTranslations, JSON_PRETTY_PRINT));
        
        $this->artisan('translate --add')
            ->expectsOutput('Mode: Adding new translations to existing ones')
            ->assertSuccessful();
        
        $translations = json_decode(File::get($this->jsonFilePath), true);
        
        // Should keep existing translations
        $this->assertArrayHasKey('Existing translation', $translations);
        $this->assertArrayHasKey('Another one', $translations);
        
        // Should add new translations
        $this->assertArrayHasKey('Hello World', $translations);
        $this->assertArrayHasKey('No Spaces', $translations);
        $this->assertArrayHasKey('Welcome to our application', $translations);
        $this->assertArrayHasKey('No Spaces Trans', $translations);
    }

    protected function createTestViewFile()
    {
        $content = <<<'EOT'
<h1>{{ __('Hello World') }}</h1>
<p>{{__('No Spaces')}}</p>
<p>{{ trans('Welcome to our application') }}</p>
<p>{{trans('No Spaces Trans')}}</p>
<p>@lang('This is a test')</p>
<p>{{ __('auth.failed') }}</p>
<p>{{ __('This has a $variable') }}</p>
<p>{{ __('This has a :placeholder') }}</p>
EOT;

        File::put($this->testViewsPath . '/test.blade.php', $content);
    }
}