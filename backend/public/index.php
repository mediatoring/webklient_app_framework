<?php

declare(strict_types=1);

/**
 * WebklientApp Framework - API Gateway
 *
 * Single entry point for all API requests.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use WebklientApp\Core\Bootstrap;

$app = new Bootstrap();
$app->boot();

// Load routes
$router = $app->getRouter();
require dirname(__DIR__) . '/config/routes.php';

// Handle the request
$app->handleRequest();
