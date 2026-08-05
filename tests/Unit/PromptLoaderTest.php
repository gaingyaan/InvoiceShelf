<?php

use App\Platform\Ai\Prompting\PromptLoader;

test('load substitutes placeholders in the chat-system template', function () {
    $prompt = PromptLoader::load('chat-system', [
        'user_name' => 'Jane Doe',
        'company_name' => 'Acme Corp',
        'today' => '2026-04-11',
    ]);

    // Substituted values present
    expect($prompt)->toContain('Jane Doe');
    expect($prompt)->toContain('Acme Corp');
    expect($prompt)->toContain('2026-04-11');

    // Raw placeholders are gone
    expect($prompt)->not->toContain('{{user_name}}');
    expect($prompt)->not->toContain('{{company_name}}');
    expect($prompt)->not->toContain('{{today}}');
});

test('load returns a trimmed template when no placeholders are provided', function () {
    $prompt = PromptLoader::load('text-generation');

    expect($prompt)->not->toBe('');
    // No leading/trailing whitespace — trim() stripped the trailing newline.
    expect($prompt)->toBe(trim($prompt));
    // Sanity-check a word known to appear in the preamble.
    expect($prompt)->toContain('writing assistant');
});

test('load throws when the template file is missing', function () {
    expect(fn () => PromptLoader::load('definitely-not-a-real-prompt'))
        ->toThrow(RuntimeException::class, 'Missing AI prompt template: definitely-not-a-real-prompt');
});
