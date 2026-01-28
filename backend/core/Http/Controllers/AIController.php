<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http\Controllers;

use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\AI\AIService;
use WebklientApp\Core\Exceptions\NotFoundException;
use WebklientApp\Core\Exceptions\ValidationException;
use WebklientApp\Core\Validation\Validator;

class AIController extends BaseController
{
    private AIService $ai;

    public function __construct()
    {
        parent::__construct();
        $this->ai = new AIService($this->db);
    }

    public function chat(Request $request): JsonResponse
    {
        $data = $request->input();
        $errors = Validator::validate($data, [
            'message' => 'required|min:1|max:10000',
            'conversation_id' => 'integer',
            'model' => 'max:100',
            'provider' => 'max:50',
        ]);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed.', $errors);
        }

        $userId = $request->getAttribute('user_id');

        $result = $this->ai->chat(
            userId: $userId,
            message: $data['message'],
            conversationId: isset($data['conversation_id']) ? (int) $data['conversation_id'] : null,
            model: $data['model'] ?? null,
            provider: $data['provider'] ?? null,
        );

        return JsonResponse::success($result);
    }

    public function stream(Request $request): void
    {
        $data = $request->input();
        $errors = Validator::validate($data, [
            'message' => 'required|min:1|max:10000',
            'conversation_id' => 'integer',
            'model' => 'max:100',
        ]);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed.', $errors);
        }

        $userId = $request->getAttribute('user_id');

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $this->ai->chatStream(
            userId: $userId,
            message: $data['message'],
            conversationId: isset($data['conversation_id']) ? (int) $data['conversation_id'] : null,
            model: $data['model'] ?? null,
            onChunk: function (string $chunk) {
                echo "data: " . json_encode(['content' => $chunk]) . "\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            },
        );

        echo "data: [DONE]\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    public function conversations(Request $request): JsonResponse
    {
        $userId = $request->getAttribute('user_id');
        $p = $this->paginationParams($request);

        $result = $this->query->table('ai_conversations')
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'DESC')
            ->paginate($p['page'], $p['per_page']);

        return JsonResponse::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function showConversation(Request $request): JsonResponse
    {
        $id = (int) $request->param('id');
        $userId = $request->getAttribute('user_id');

        $conversation = $this->query->table('ai_conversations')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$conversation) {
            throw new NotFoundException('Conversation not found.');
        }

        $messages = $this->query->table('ai_messages')
            ->where('conversation_id', $id)
            ->orderBy('created_at')
            ->get();

        $conversation['messages'] = $messages;

        return JsonResponse::success($conversation);
    }

    public function deleteConversation(Request $request): JsonResponse
    {
        $id = (int) $request->param('id');
        $userId = $request->getAttribute('user_id');

        $conversation = $this->query->table('ai_conversations')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$conversation) {
            throw new NotFoundException('Conversation not found.');
        }

        $this->query->table('ai_messages')->where('conversation_id', $id)->delete();
        $this->query->table('ai_conversations')->where('id', $id)->delete();

        return JsonResponse::success(null, 'Conversation deleted.');
    }

    public function usage(Request $request): JsonResponse
    {
        $userId = $request->getAttribute('user_id');

        $stats = $this->db->fetchAll(
            "SELECT provider, model, COUNT(*) as request_count, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens
             FROM ai_messages WHERE conversation_id IN (SELECT id FROM ai_conversations WHERE user_id = ?) AND role = 'assistant'
             GROUP BY provider, model",
            [$userId]
        );

        return JsonResponse::success($stats);
    }

    public function models(Request $request): JsonResponse
    {
        return JsonResponse::success($this->ai->getAvailableModels());
    }
}
