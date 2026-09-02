<?php

namespace App\Services;

use App\ChatTools\ChatToolContract;
use App\Models\User;

class ChatToolsService
{
    /** @var array<string, ChatToolContract> */
    private array $tools = [];

    public function __construct()
    {
        foreach (glob(app_path('ChatTools/*.php')) ?: [] as $file) {
            $className = 'App\\ChatTools\\'.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);

            if ($reflection->isAbstract() || ! $reflection->implementsInterface(ChatToolContract::class)) {
                continue;
            }

            $tool = app($className);
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * Function declarations sesuai format OpenAI-compatible function calling.
     *
     * @return array<int, array<string, mixed>>
     */
    public function declarations(): array
    {
        return array_map(
            fn (ChatToolContract $tool): array => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $this->toJsonSchema($tool->parameters()),
            ],
            array_values($this->tools),
        );
    }

    /**
     * Filtered declarations for channel-gated calls (e.g. telegram).
     *
     * @param  array<int, string>  $allowed
     * @return array<int, array<string, mixed>>
     */
    public function declarationsFiltered(array $allowed): array
    {
        return array_values(array_filter(
            $this->declarations(),
            fn (array $decl): bool => in_array($decl['name'], $allowed, true),
        ));
    }

    /**
     * Ubah skema parameter Gemini (huruf besar, properties wajib object)
     * menjadi JSON Schema yang dipahami endpoint OpenAI-compatible.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function toJsonSchema(array $schema): array
    {
        $typeMap = [
            'OBJECT' => 'object',
            'STRING' => 'string',
            'INTEGER' => 'integer',
            'NUMBER' => 'number',
            'BOOLEAN' => 'boolean',
            'ARRAY' => 'array',
        ];

        $converted = [];

        foreach ($schema as $key => $value) {
            if ($key === 'type' && is_string($value)) {
                $converted[$key] = $typeMap[$value] ?? strtolower($value);
            } elseif (is_array($value)) {
                if ($value === []) {
                    $converted[$key] = $key === 'required' ? [] : new \stdClass;
                } else {
                    $converted[$key] = $this->toJsonSchema($value);
                }
            } else {
                $converted[$key] = $value;
            }
        }

        return $converted;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{data: mixed}|array{error: string}
     */
    public function handle(string $name, array $args, User $user): array
    {
        if (! isset($this->tools[$name])) {
            return ['error' => "Tool '$name' tidak ditemukan."];
        }

        return $this->tools[$name]->handle($args, $user);
    }
}
