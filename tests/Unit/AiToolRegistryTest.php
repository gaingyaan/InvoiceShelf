<?php

use App\Platform\Ai\Application\AiToolRegistry;
use App\Platform\Ai\Application\Tools\AiTool;

/**
 * A stub tool for exercising the registry without touching any real DB tables.
 */
class FakeAiTool extends AiTool
{
    public array $lastArgs = [];

    public int $lastCompanyId = 0;

    public int $lastUserId = 0;

    public function __construct(
        private readonly string $toolName = 'fake_tool',
    ) {}

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): string
    {
        return 'A fake tool for tests.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'echo' => ['type' => 'string'],
            ],
            'required' => [],
        ];
    }

    public function requiredAbility(): ?array
    {
        return null;
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $this->lastArgs = $arguments;
        $this->lastCompanyId = $companyId;
        $this->lastUserId = $userId;

        return ['echo' => $arguments['echo'] ?? null];
    }
}

test('register stores a tool by its name', function () {
    $registry = new AiToolRegistry;
    $tool = new FakeAiTool;

    $registry->register($tool);

    expect($registry->get('fake_tool'))->toBe($tool);
    expect($registry->all())->toHaveKey('fake_tool');
});

test('schemas returns OpenAI-format tool entries for every registered tool', function () {
    $registry = new AiToolRegistry;
    $registry->register(new FakeAiTool('alpha'));
    $registry->register(new FakeAiTool('beta'));

    $schemas = $registry->schemas(1);

    expect($schemas)->toHaveCount(2);
    expect($schemas[0]['type'])->toBe('function');
    expect($schemas[0]['function']['name'])->toBe('alpha');
    expect($schemas[1]['function']['name'])->toBe('beta');
});

test('execute injects companyId and userId into the tool and returns its result', function () {
    $registry = new AiToolRegistry;
    $tool = new FakeAiTool;
    $registry->register($tool);

    $result = $registry->execute('fake_tool', ['echo' => 'hello'], 42, 7);

    expect($result)->toEqual(['echo' => 'hello']);
    expect($tool->lastCompanyId)->toBe(42);
    expect($tool->lastUserId)->toBe(7);
});

test('execute throws for unknown tool names', function () {
    $registry = new AiToolRegistry;

    expect(fn () => $registry->execute('nonexistent', [], 1, 1))
        ->toThrow(InvalidArgumentException::class);
});
