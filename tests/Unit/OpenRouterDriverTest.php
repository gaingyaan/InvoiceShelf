<?php

use App\Platform\Ai\Drivers\OpenRouterDriver;
use App\Platform\Ai\Exceptions\AiException;

/**
 * Runtime SSRF backstop: even if a private base URL slips past validation (e.g.
 * config saved before the rule existed, or DNS rebinding), the driver must refuse
 * to make the request.
 */
test('rejects a private base URL before making any request', function () {
    $driver = new OpenRouterDriver('test-key', ['base_url' => 'http://169.254.169.254']);

    expect(fn () => $driver->validateConnection())->toThrow(AiException::class);
});

test('the thrown AI exception carries the invalid_base_url error key', function () {
    $driver = new OpenRouterDriver('test-key', ['base_url' => 'http://127.0.0.1:11434']);

    try {
        $driver->validateConnection();
        test()->fail('Expected AiException was not thrown');
    } catch (AiException $e) {
        expect($e->errorKey)->toBe('invalid_base_url');
    }
});
