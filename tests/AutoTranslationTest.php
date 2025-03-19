<?php

namespace BrnBio\LaravelAutoTranslation\Tests;

use BrnBio\LaravelAutoTranslation\AutoTranslation;

class AutoTranslationTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated()
    {
        $autoTranslation = new AutoTranslation();
        
        $this->assertInstanceOf(AutoTranslation::class, $autoTranslation);
    }
    
    /** @test */
    public function it_returns_empty_array_before_implementation()
    {
        $autoTranslation = new AutoTranslation();
        
        $result = $autoTranslation->translate('Hello, world!');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}