<?php

declare(strict_types=1);

namespace WebklientApp\Core\AI;

class OpenAIClient implements AIClientInterface
{
    private string $apiKey;
    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getDefaultModel(): string
    {
        return getenv('OPENAI_DEFAULT_MODEL') ?: 'gpt-4';
    }

    public function chat(array $messages, string $model): array
    {
        $payload = [
            'model' => $model,
            'messages' => $this->formatMessages($messages),
        ];

        $response = $this->request('POST', '/chat/completions', $payload);

        return [
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'usage' => $response['usage'] ?? [],
        ];
    }

    public function chatStream(array $messages, string $model, callable $onChunk): void
    {
        $payload = [
            'model' => $model,
            'messages' => $this->formatMessages($messages),
            'stream' => true,
        ];

        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($onChunk) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, 'data: ') && $line !== 'data: [DONE]') {
                        $json = json_decode(substr($line, 6), true);
                        $content = $json['choices'][0]['delta']['content'] ?? '';
                        if ($content !== '') {
                            $onChunk($content);
                        }
                    }
                }
                return strlen($data);
            },
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    private function formatMessages(array $messages): array
    {
        return array_map(fn($m) => [
            'role' => $m['role'],
            'content' => $m['content'],
        ], $messages);
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
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
                'OpenAI API error: ' . ($error['error']['message'] ?? $response),
                $httpCode
            );
        }

        return json_decode($response, true);
    }
}
