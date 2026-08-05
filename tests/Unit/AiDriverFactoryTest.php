<?php

use App\Platform\Ai\Contracts\AiDriver;
use App\Platform\Ai\Data\AiChatResponse;
use App\Platform\Ai\Drivers\AiDriverFactory;
use App\Platform\Ai\Drivers\OpenRouterDriver;
use InvoiceShelf\Modules\Registry;

test('make resolves the built-in openrouter driver', function () {
    $driver = AiDriverFactory::make('openrouter', 'fake-key');

    expect($driver)->toBeInstanceOf(OpenRouterDriver::class);
});

test('make resolves Registry-only drivers via metadata', function () {
    $fakeClass = new class('', []) extends AiDriver
    {
        public function chatCompletion(array $messages, string $model, array $tools = [], array $options = []): AiChatResponse
        {
            return new AiChatResponse(message: 'test');
        }

        public function textCompletion(string $prompt, string $model, array $options = []): string
        {
            return 'test';
        }

        public function validateConnection(): array
        {
            return ['ok' => true];
        }
    };

    Registry::registerAiDriver('registry_only_ai', [
        'class' => $fakeClass::class,
        'label' => 'test.ai.label',
    ]);

    try {
        $driver = AiDriverFactory::make('registry_only_ai', 'fake-key');
        expect($driver)->toBeInstanceOf(AiDriver::class);
    } finally {
        unset(Registry::$drivers['ai']['registry_only_ai']);
    }
});

test('make throws for unknown drivers', function () {
    expect(fn () => AiDriverFactory::make('definitely_not_a_real_ai_driver', 'k'))
        ->toThrow(InvalidArgumentException::class);
});

test('availableDrivers merges built-in and Registry-registered drivers', function () {
    Registry::registerAiDriver('extra_ai_driver', [
        'class' => OpenRouterDriver::class,
        'label' => 'extra.label',
    ]);

    try {
        $available = AiDriverFactory::availableDrivers();

        expect($available)
            ->toContain('openrouter')
            ->toContain('extra_ai_driver');
    } finally {
        unset(Registry::$drivers['ai']['extra_ai_driver']);
    }
});
