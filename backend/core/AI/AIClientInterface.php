<?php

declare(strict_types=1);

namespace WebklientApp\Core\AI;

interface AIClientInterface
{
    public function chat(array $messages, string $model): array;

    public function chatStream(array $messages, string $model, callable $onChunk): void;

    public function getDefaultModel(): string;
}
