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
     * Function declarations sesuai format Gemini API.
     *
     * @return array<int, array<string, mixed>>
     */
    public function declarations(): array
    {
        return array_map(
            fn (ChatToolContract $tool): array => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
            array_values($this->tools),
        );
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
