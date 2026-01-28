<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http\Controllers;

use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Exceptions\NotFoundException;

class ActivityLogController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $p = $this->paginationParams($request);
        $q = $this->query->table('activity_log');

        if ($userId = $request->get('user_id')) {
            $q = $q->where('user_id', (int) $userId);
        }
        if ($actionType = $request->get('action_type')) {
            $q = $q->where('action_type', $actionType);
        }
        if ($resourceType = $request->get('resource_type')) {
            $q = $q->where('resource_type', $resourceType);
        }
        if ($from = $request->get('from')) {
            $q = $q->where('created_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $q = $q->where('created_at', '<=', $to);
        }
        if ($request->get('admin_only') === '1') {
            $q = $q->where('is_admin_action', 1);
        }

        $result = $q->orderBy('created_at', 'DESC')->paginate($p['page'], $p['per_page']);
        return JsonResponse::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function show(Request $request): JsonResponse
    {
        $id = (int) $request->param('id');
        $entry = $this->query->table('activity_log')->where('id', $id)->first();
        if (!$entry) {
            throw new NotFoundException('Activity log entry not found.');
        }
        return JsonResponse::success($entry);
    }

    public function my(Request $request): JsonResponse
    {
        $userId = $request->getAttribute('user_id');
        $p = $this->paginationParams($request);

        $result = $this->query->table('activity_log')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->paginate($p['page'], $p['per_page']);

        return JsonResponse::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function stats(Request $request): JsonResponse
    {
        $stats = [
            'total' => $this->query->table('activity_log')->count(),
            'today' => $this->query->table('activity_log')->where('created_at', '>=', date('Y-m-d 00:00:00'))->count(),
            'by_action' => $this->db->fetchAll(
                "SELECT action_type, COUNT(*) as count FROM activity_log GROUP BY action_type ORDER BY count DESC"
            ),
            'by_resource' => $this->db->fetchAll(
                "SELECT resource_type, COUNT(*) as count FROM activity_log WHERE resource_type IS NOT NULL GROUP BY resource_type ORDER BY count DESC"
            ),
        ];

        return JsonResponse::success($stats);
    }
}
