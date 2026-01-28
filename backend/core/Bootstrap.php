<?php

declare(strict_types=1);

namespace WebklientApp\Core;

use WebklientApp\Core\Database\Connection;
use WebklientApp\Core\Http\Router;
use WebklientApp\Core\Http\Request;
use WebklientApp\Core\Http\JsonResponse;
use WebklientApp\Core\Exceptions\ExceptionHandler;

class Bootstrap
{
    private ConfigLoader $config;
    private ?Connection $db = null;
    private Router $router;

    public function __construct()
    {
        $this->config = ConfigLoader::getInstance();
        $this->config->loadConfigDirectory(dirname(__DIR__) . '/config');
        $this->router = new Router();
    }

    public function boot(): void
    {
        $this->setErrorHandling();
        $this->setTimezone();
        $this->validateRequiredConfig();
    }

    private function setErrorHandling(): void
    {
        $debug = $this->config->isDebug();

        error_reporting($debug ? E_ALL : 0);
        ini_set('display_errors', $debug ? '1' : '0');

        set_exception_handler([ExceptionHandler::class, 'handle']);
    }

    private function setTimezone(): void
    {
        date_default_timezone_set(
            $this->config->get('app.timezone', 'UTC')
        );
    }

    private function validateRequiredConfig(): void
    {
        $this->config->require('APP_KEY');
    }

    public function getConfig(): ConfigLoader
    {
        return $this->config;
    }

    public function getDatabase(): Connection
    {
        if ($this->db === null) {
            $dbConfig = $this->config->get('database');
            $this->db = new Connection($dbConfig);
        }
        return $this->db;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function handleRequest(): void
    {
        $request = Request::capture();

        try {
            $response = $this->router->dispatch($request);
        } catch (\Throwable $e) {
            $response = ExceptionHandler::toResponse($e, $this->config->isDebug());
        }

        $response->send();
    }
}
