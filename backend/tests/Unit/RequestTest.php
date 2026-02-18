<?php

declare(strict_types=1);

namespace WebklientApp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebklientApp\Core\Http\Request;

class RequestTest extends TestCase
{
    private function makeRequest(
        string $method = 'GET',
        string $uri = '/test',
        array $query = [],
        array $body = [],
        array $headers = [],
        array $server = [],
        array $files = []
    ): Request {
        return new Request($method, $uri, $query, $body, $headers, $server, $files);
    }

    public function testMethodIsUppercased(): void
    {
        $request = $this->makeRequest('get');
        $this->assertSame('GET', $request->method());
    }

    public function testPathExtractedFromUri(): void
    {
        $request = $this->makeRequest('GET', '/api/users?page=1');
        $this->assertSame('/api/users', $request->path());
    }

    public function testPathDefaultsToSlash(): void
    {
        $request = $this->makeRequest('GET', '');
        $this->assertSame('/', $request->path());
    }

    public function testUri(): void
    {
        $request = $this->makeRequest('GET', '/api/test?foo=bar');
        $this->assertSame('/api/test?foo=bar', $request->uri());
    }

    public function testQueryParameter(): void
    {
        $request = $this->makeRequest('GET', '/test', ['page' => '2', 'sort' => 'name']);
        $this->assertSame('2', $request->query('page'));
        $this->assertSame('name', $request->query('sort'));
        $this->assertNull($request->query('missing'));
        $this->assertSame('default', $request->query('missing', 'default'));
    }

    public function testQueryAllParameters(): void
    {
        $query = ['a' => '1', 'b' => '2'];
        $request = $this->makeRequest('GET', '/test', $query);
        $this->assertSame($query, $request->query());
    }

    public function testGetAlias(): void
    {
        $request = $this->makeRequest('GET', '/test', ['key' => 'value']);
        $this->assertSame('value', $request->get('key'));
        $this->assertSame('fallback', $request->get('missing', 'fallback'));
    }

    public function testInput(): void
    {
        $body = ['name' => 'John', 'email' => 'john@test.com'];
        $request = $this->makeRequest('POST', '/test', [], $body);
        $this->assertSame('John', $request->input('name'));
        $this->assertSame('john@test.com', $request->input('email'));
        $this->assertNull($request->input('missing'));
        $this->assertSame('x', $request->input('missing', 'x'));
    }

    public function testInputAll(): void
    {
        $body = ['name' => 'John'];
        $request = $this->makeRequest('POST', '/test', [], $body);
        $this->assertSame($body, $request->input());
    }

    public function testAll(): void
    {
        $request = $this->makeRequest('POST', '/test', ['q' => 'search'], ['name' => 'John']);
        $all = $request->all();
        $this->assertSame('search', $all['q']);
        $this->assertSame('John', $all['name']);
    }

    public function testHeader(): void
    {
        $request = $this->makeRequest('GET', '/test', [], [], ['content-type' => 'application/json']);
        $this->assertSame('application/json', $request->header('content-type'));
        $this->assertSame('application/json', $request->header('Content-Type'));
        $this->assertNull($request->header('x-missing'));
    }

    public function testHeaders(): void
    {
        $headers = ['content-type' => 'application/json', 'authorization' => 'Bearer xyz'];
        $request = $this->makeRequest('GET', '/test', [], [], $headers);
        $this->assertSame($headers, $request->headers());
    }

    public function testBearerToken(): void
    {
        $request = $this->makeRequest('GET', '/test', [], [], ['authorization' => 'Bearer my-token-123']);
        $this->assertSame('my-token-123', $request->bearerToken());
    }

    public function testBearerTokenReturnsNullWhenMissing(): void
    {
        $request = $this->makeRequest('GET', '/test');
        $this->assertNull($request->bearerToken());
    }

    public function testBearerTokenReturnsNullForNonBearerAuth(): void
    {
        $request = $this->makeRequest('GET', '/test', [], [], ['authorization' => 'Basic dXNlcjpwYXNz']);
        $this->assertNull($request->bearerToken());
    }

    public function testBearerTokenStripsControlChars(): void
    {
        $request = $this->makeRequest('GET', '/test', [], [], [
            'authorization' => "Bearer token\r\nX-Injected: evil",
        ]);
        $token = $request->bearerToken();
        $this->assertNotNull($token);
        $this->assertStringNotContainsString("\r", $token);
        $this->assertStringNotContainsString("\n", $token);
        $this->assertStringNotContainsString("\0", $token);
    }

    public function testIpFromRemoteAddr(): void
    {
        $request = $this->makeRequest('GET', '/test', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);
        $this->assertSame('192.168.1.1', $request->ip());
    }

    public function testIpFromForwardedFor(): void
    {
        $request = $this->makeRequest('GET', '/test', [], [], [], [
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1',
            'REMOTE_ADDR' => '192.168.1.1',
        ]);
        $this->assertSame('10.0.0.1', $request->ip());
    }

    public function testIpFromForwardedForTakesFirstOnly(): void
    {
        $request = $this->makeRequest('GET', '/test', [], [], [], [
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1, 172.16.0.1, 192.168.1.1',
            'REMOTE_ADDR' => '203.0.113.1',
        ]);
        $this->assertSame('10.0.0.1', $request->ip());
    }

    public function testIpFromForwardedForValidatesIp(): void
    {
        $request = $this->makeRequest('GET', '/test', [], [], [], [
            'HTTP_X_FORWARDED_FOR' => 'not-an-ip',
            'HTTP_X_REAL_IP' => '10.0.0.2',
            'REMOTE_ADDR' => '192.168.1.1',
        ]);
        $this->assertSame('10.0.0.2', $request->ip());
    }

    public function testIpFromRealIp(): void
    {
        $request = $this->makeRequest('GET', '/test', [], [], [], [
            'HTTP_X_REAL_IP' => '10.0.0.2',
            'REMOTE_ADDR' => '192.168.1.1',
        ]);
        $this->assertSame('10.0.0.2', $request->ip());
    }

    public function testIpDefaultsFallback(): void
    {
        $request = $this->makeRequest('GET', '/test');
        $this->assertSame('0.0.0.0', $request->ip());
    }

    public function testUserAgent(): void
    {
        $request = $this->makeRequest('GET', '/test', [], [], [], ['HTTP_USER_AGENT' => 'TestBot/1.0']);
        $this->assertSame('TestBot/1.0', $request->userAgent());
    }

    public function testUserAgentEmpty(): void
    {
        $request = $this->makeRequest('GET', '/test');
        $this->assertSame('', $request->userAgent());
    }

    public function testIsMethod(): void
    {
        $request = $this->makeRequest('POST');
        $this->assertTrue($request->isMethod('post'));
        $this->assertTrue($request->isMethod('POST'));
        $this->assertFalse($request->isMethod('GET'));
    }

    public function testIsMutation(): void
    {
        $this->assertTrue($this->makeRequest('POST')->isMutation());
        $this->assertTrue($this->makeRequest('PUT')->isMutation());
        $this->assertTrue($this->makeRequest('PATCH')->isMutation());
        $this->assertTrue($this->makeRequest('DELETE')->isMutation());
        $this->assertFalse($this->makeRequest('GET')->isMutation());
        $this->assertFalse($this->makeRequest('OPTIONS')->isMutation());
    }

    public function testFile(): void
    {
        $files = ['avatar' => ['name' => 'photo.jpg', 'size' => 1024]];
        $request = $this->makeRequest('POST', '/upload', [], [], [], [], $files);
        $this->assertSame('photo.jpg', $request->file('avatar')['name']);
        $this->assertNull($request->file('missing'));
    }

    public function testAttributes(): void
    {
        $request = $this->makeRequest('GET', '/test');
        $request->setAttribute('user_id', 42);
        $this->assertSame(42, $request->getAttribute('user_id'));
        $this->assertNull($request->getAttribute('missing'));
        $this->assertSame('default', $request->getAttribute('missing', 'default'));
    }

    public function testUser(): void
    {
        $request = $this->makeRequest('GET', '/test');
        $this->assertNull($request->user());

        $user = ['id' => 1, 'name' => 'Admin'];
        $request->setAttribute('user', $user);
        $this->assertSame($user, $request->user());
    }

    public function testRouteParams(): void
    {
        $request = $this->makeRequest('GET', '/users/42');
        $request->setRouteParams(['id' => '42']);
        $this->assertSame('42', $request->param('id'));
        $this->assertNull($request->param('missing'));
        $this->assertSame('default', $request->param('missing', 'default'));
    }
}
