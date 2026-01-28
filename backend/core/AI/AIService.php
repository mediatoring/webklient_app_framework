<?php

declare(strict_types=1);

namespace WebklientApp\Core\AI;

use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\Database\Connection;
use WebklientApp\Core\Database\QueryBuilder;

class AIService
{
    private Connection $db;
    private QueryBuilder $query;
    private ConfigLoader $config;

    public function __construct(Connection $db)
    {
        $this->db = $db;
        $this->query = new QueryBuilder($db);
        $this->config = ConfigLoader::getInstance();
    }

    public function chat(
        int $userId,
        string $message,
        ?int $conversationId = null,
        ?string $model = null,
        ?string $provider = null,
    ): array {
        $provider = $provider ?? $this->config->env('AI_DEFAULT_PROVIDER', 'openai');
        $client = $this->getClient($provider);
        $model = $model ?? $client->getDefaultModel();

        // Get or create conversation
        if ($conversationId) {
            $conversation = $this->query->table('ai_conversations')
                ->where('id', $conversationId)
                ->where('user_id', $userId)
                ->first();
            if (!$conversation) {
                throw new \WebklientApp\Core\Exceptions\NotFoundException('Conversation not found.');
            }
        } else {
            $conversationId = (int) $this->query->table('ai_conversations')->insert([
                'user_id' => $userId,
                'title' => mb_substr($message, 0, 100),
                'provider' => $provider,
                'model' => $model,
            ]);
        }

        // Store user message
        $this->query->table('ai_messages')->insert([
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => $message,
            'provider' => $provider,
            'model' => $model,
        ]);

        // Get conversation history
        $history = $this->query->table('ai_messages')
            ->select('role', 'content')
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get();

        // Call AI
        $response = $client->chat($history, $model);

        // Store assistant message
        $this->query->table('ai_messages')->insert([
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => $response['content'],
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
        ]);

        // Update conversation
        $this->query->table('ai_conversations')
            ->where('id', $conversationId)
            ->update(['updated_at' => date('Y-m-d H:i:s')]);

        return [
            'conversation_id' => $conversationId,
            'message' => $response['content'],
            'model' => $model,
            'provider' => $provider,
            'usage' => $response['usage'] ?? null,
        ];
    }

    public function chatStream(
        int $userId,
        string $message,
        ?int $conversationId = null,
        ?string $model = null,
        ?callable $onChunk = null,
    ): void {
        $provider = $this->config->env('AI_DEFAULT_PROVIDER', 'openai');
        $client = $this->getClient($provider);
        $model = $model ?? $client->getDefaultModel();

        if (!$conversationId) {
            $conversationId = (int) $this->query->table('ai_conversations')->insert([
                'user_id' => $userId,
                'title' => mb_substr($message, 0, 100),
                'provider' => $provider,
                'model' => $model,
            ]);
        }

        $this->query->table('ai_messages')->insert([
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => $message,
            'provider' => $provider,
            'model' => $model,
        ]);

        $history = $this->query->table('ai_messages')
            ->select('role', 'content')
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get();

        $fullResponse = '';
        $client->chatStream($history, $model, function (string $chunk) use (&$fullResponse, $onChunk) {
            $fullResponse .= $chunk;
            if ($onChunk) {
                $onChunk($chunk);
            }
        });

        $this->query->table('ai_messages')->insert([
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => $fullResponse,
            'provider' => $provider,
            'model' => $model,
        ]);
    }

    public function getAvailableModels(): array
    {
        $models = [];

        if ($this->config->env('OPENAI_API_KEY')) {
            $models['openai'] = [
                ['id' => 'gpt-4', 'name' => 'GPT-4'],
                ['id' => 'gpt-4-turbo', 'name' => 'GPT-4 Turbo'],
                ['id' => 'gpt-3.5-turbo', 'name' => 'GPT-3.5 Turbo'],
            ];
        }

        if ($this->config->env('ANTHROPIC_API_KEY')) {
            $models['anthropic'] = [
                ['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4'],
                ['id' => 'claude-3-5-haiku-20241022', 'name' => 'Claude 3.5 Haiku'],
            ];
        }

        return $models;
    }

    private function getClient(string $provider): AIClientInterface
    {
        return match ($provider) {
            'openai' => new OpenAIClient($this->config->env('OPENAI_API_KEY', '')),
            'anthropic' => new AnthropicClient($this->config->env('ANTHROPIC_API_KEY', '')),
            default => throw new \InvalidArgumentException("Unknown AI provider: {$provider}"),
        };
    }
}
