<?php

namespace Tests\Support;

use App\Platform\Ai\Contracts\AiDriver;
use App\Platform\Ai\Data\AiChatResponse;

/**
 * Test double for AiDriver that returns pre-queued responses from an array, so
 * tests can script tool-call loops without hitting any real LLM API.
 *
 * It also records the last `messages` and `tools` payloads handed to the driver,
 * letting tests assert which tool schemas were exposed to the model.
 *
 * Usage:
 *   ScriptedAiDriver::setResponses([
 *       new AiChatResponse(message: null, toolCalls: [...]),
 *       new AiChatResponse(message: 'Final answer'),
 *   ]);
 */
class ScriptedAiDriver extends AiDriver
{
    /** @var array<int, AiChatResponse> */
    public static array $responses = [];

    public static int $callCount = 0;

    /** @var array<int, array<string, mixed>> */
    public static array $lastMessages = [];

    /** @var array<int, array<string, mixed>> */
    public static array $lastTools = [];

    public static function reset(): void
    {
        self::$responses = [];
        self::$callCount = 0;
        self::$lastMessages = [];
        self::$lastTools = [];
    }

    /**
     * @param  array<int, AiChatResponse>  $responses
     */
    public static function setResponses(array $responses): void
    {
        self::$responses = $responses;
        self::$callCount = 0;
    }

    public function chatCompletion(
        array $messages,
        string $model,
        array $tools = [],
        array $options = [],
    ): AiChatResponse {
        self::$lastMessages = $messages;
        self::$lastTools = $tools;
        $response = self::$responses[self::$callCount] ?? new AiChatResponse(message: 'Default test reply');
        self::$callCount++;

        return $response;
    }

    public function textCompletion(string $prompt, string $model, array $options = []): string
    {
        return 'text completion test';
    }

    public function validateConnection(): array
    {
        return ['ok' => true];
    }
}
