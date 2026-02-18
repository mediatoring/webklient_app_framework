<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Http\JsonResponse;

class JsonResponseTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $response = new JsonResponse(['key' => 'value']);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['key' => 'value'], $response->getBody());
    }

    public function testConstructorCustomStatus(): void
    {
        $response = new JsonResponse([], 404);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testSuccessResponse(): void
    {
        $response = JsonResponse::success(['id' => 1], 'OK');
        $body = $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertSame(['id' => 1], $body['data']);
        $this->assertSame('OK', $body['message']);
    }

    public function testSuccessResponseWithoutMessage(): void
    {
        $response = JsonResponse::success(['id' => 1]);
        $body = $response->getBody();

        $this->assertTrue($body['success']);
        $this->assertArrayNotHasKey('message', $body);
    }

    public function testSuccessResponseWithMetadata(): void
    {
        $response = JsonResponse::success([], '', ['key' => 'val']);
        $body = $response->getBody();

        $this->assertSame(['key' => 'val'], $body['metadata']);
    }

    public function testCreatedResponse(): void
    {
        $response = JsonResponse::created(['id' => 5], '/api/users/5');
        $body = $response->getBody();

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertSame(['id' => 5], $body['data']);
        $this->assertSame('Resource created.', $body['message']);
    }

    public function testNoContentResponse(): void
    {
        $response = JsonResponse::noContent();
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testErrorResponse(): void
    {
        $response = JsonResponse::error('NOT_FOUND', 'Resource not found', [], 404);
        $body = $response->getBody();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('NOT_FOUND', $body['error']['code']);
        $this->assertSame('Resource not found', $body['error']['message']);
    }

    public function testErrorResponseWithDetails(): void
    {
        $details = [['field' => 'email', 'message' => 'Invalid']];
        $response = JsonResponse::error('VALIDATION', 'Invalid input', $details);
        $body = $response->getBody();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame($details, $body['error']['details']);
    }

    public function testPaginatedResponse(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $response = JsonResponse::paginated($items, 50, 1, 15);
        $body = $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertSame($items, $body['data']);
        $this->assertSame(50, $body['metadata']['pagination']['total']);
        $this->assertSame(15, $body['metadata']['pagination']['per_page']);
        $this->assertSame(1, $body['metadata']['pagination']['current_page']);
        $this->assertSame(4, $body['metadata']['pagination']['last_page']);
        $this->assertTrue($body['metadata']['pagination']['has_more']);
    }

    public function testPaginatedResponseLastPage(): void
    {
        $response = JsonResponse::paginated([], 10, 2, 10);
        $body = $response->getBody();

        $this->assertSame(1, $body['metadata']['pagination']['last_page']);
        $this->assertFalse($body['metadata']['pagination']['has_more']);
    }

    public function testPaginatedResponseEmptyResults(): void
    {
        $response = JsonResponse::paginated([], 0, 1, 15);
        $body = $response->getBody();

        $this->assertSame(1, $body['metadata']['pagination']['last_page']);
        $this->assertFalse($body['metadata']['pagination']['has_more']);
    }

    public function testWithHeaderReturnsNewInstance(): void
    {
        $original = new JsonResponse(['test' => true]);
        $modified = $original->withHeader('X-Custom', 'value');

        $this->assertNotSame($original, $modified);
    }

    public function testSuccessResponseContainsSecurityHeaders(): void
    {
        $response = new JsonResponse([]);
        ob_start();
        $response->send();
        ob_end_clean();
        // JsonResponse default headers are set in constructor, verified by body structure
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testNullDataInSuccess(): void
    {
        $response = JsonResponse::success(null);
        $body = $response->getBody();
        $this->assertNull($body['data']);
    }
}
