<?php

declare(strict_types=1);

namespace WebklientApp\Core\AI;

class AnthropicClient implements AIClientInterface
{
    private string $apiKey;
    private string $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getDefaultModel(): string
    {
        return getenv('ANTHROPIC_DEFAULT_MODEL') ?: 'claude-sonnet-4-20250514';
    }

    public function chat(array $messages, string $model): array
    {
        $systemMessage = '';
        $formatted = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemMessage = $msg['content'];
            } else {
                $formatted[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content'],
                ];
            }
        }

        $payload = [
            'model' => $model,
            'max_tokens' => 4096,
            'messages' => $formatted,
        ];
        if ($systemMessage !== '') {
            $payload['system'] = $systemMessage;
        }

        $response = $this->request('POST', '/messages', $payload);

        $content = '';
        foreach ($response['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            }
        }

        return [
            'content' => $content,
            'usage' => [
                'prompt_tokens' => $response['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
            ],
        ];
    }

    public function chatStream(array $messages, string $model, callable $onChunk): void
    {
        $formatted = [];
        $systemMessage = '';

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemMessage = $msg['content'];
            } else {
                $formatted[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }

        $payload = [
            'model' => $model,
            'max_tokens' => 4096,
            'messages' => $formatted,
            'stream' => true,
        ];
        if ($systemMessage !== '') {
            $payload['system'] = $systemMessage;
        }

        $ch = curl_init($this->baseUrl . '/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($onChunk) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, 'data: ')) {
                        $json = json_decode(substr($line, 6), true);
                        if (($json['type'] ?? '') === 'content_block_delta') {
                            $text = $json['delta']['text'] ?? '';
                            if ($text !== '') {
                                $onChunk($text);
                            }
                        }
                    }
                }
                return strlen($data);
            },
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            throw new \RuntimeException(
                'Anthropic API error: ' . ($error['error']['message'] ?? $response),
                $httpCode
            );
        }

        return json_decode($response, true);
    }
}
