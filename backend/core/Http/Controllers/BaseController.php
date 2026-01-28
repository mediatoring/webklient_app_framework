<?php

declare(strict_types=1);

namespace WebklientApp\Core\Http\Controllers;

use WebklientApp\Core\Database\Connection;
use WebklientApp\Core\Database\QueryBuilder;
use WebklientApp\Core\ConfigLoader;
use WebklientApp\Core\Http\JsonResponse;

abstract class BaseController
{
    protected Connection $db;
    protected QueryBuilder $query;

    public function __construct()
    {
        $config = ConfigLoader::getInstance();
        $this->db = new Connection($config->get('database'));
        $this->query = new QueryBuilder($this->db);
    }

    protected function paginationParams(\WebklientApp\Core\Http\Request $request): array
    {
        return [
            'page' => max(1, (int) $request->get('page', 1)),
            'per_page' => min(100, max(1, (int) $request->get('per_page', 15))),
            'sort' => $request->get('sort', 'id'),
            'order' => strtoupper($request->get('order', 'ASC')) === 'DESC' ? 'DESC' : 'ASC',
        ];
    }
}
