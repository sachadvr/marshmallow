<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Http\EventListener\SameOriginListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SameOriginListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], 0);
    }

    /**
     * @return iterable<string, array{string, string, array<string, string>}>
     */
    public static function acceptedRequests(): iterable
    {
        yield 'no browser headers (curl, scripts)' => ['POST', '/api/files', []];

        yield 'same Origin' => ['PUT', '/api/text', ['HTTP_ORIGIN' => 'http://app.local']];

        yield 'same Origin, other case' => ['PUT', '/api/text', ['HTTP_ORIGIN' => 'HTTP://APP.LOCAL']];

        yield 'Sec-Fetch-Site same-origin' => ['PUT', '/api/text', ['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_ORIGIN' => 'http://app.local']];

        yield 'Sec-Fetch-Site same-site' => ['PUT', '/api/text', ['HTTP_SEC_FETCH_SITE' => 'same-site']];

        yield 'safe method' => ['GET', '/api/text', ['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'https://evil.example']];

        yield 'outside /api' => ['POST', '/elsewhere', ['HTTP_ORIGIN' => 'https://evil.example']];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('acceptedRequests')]
    public function testAccepts(string $method, string $path, array $server): void
    {
        $this->expectNotToPerformAssertions();

        new SameOriginListener()($this->event(Request::create('http://app.local'.$path, $method, server: $server)));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function refusedRequests(): iterable
    {
        yield 'Sec-Fetch-Site cross-site' => [['HTTP_SEC_FETCH_SITE' => 'cross-site']];

        yield 'cross-site even with a same Origin' => [['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'http://app.local']];

        yield 'foreign Origin' => [['HTTP_ORIGIN' => 'https://evil.example']];

        yield 'foreign port' => [['HTTP_ORIGIN' => 'http://app.local:8081']];

        yield 'foreign scheme' => [['HTTP_ORIGIN' => 'https://app.local']];

        yield 'subdomain' => [['HTTP_ORIGIN' => 'http://evil.app.local']];

        yield 'opaque Origin' => [['HTTP_ORIGIN' => 'null']];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('refusedRequests')]
    public function testRefusesWithForbidden(array $server): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        new SameOriginListener()($this->event(Request::create('http://app.local/api/files', 'POST', server: $server)));
    }

    public function testComparesWithTheForwardedHostOfATrustedProxy(): void
    {
        $this->expectNotToPerformAssertions();
        Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO);

        $request = Request::create('http://app:80/api/text', 'PUT', server: [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_HOST' => 'public.example',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_ORIGIN' => 'https://public.example',
        ]);

        new SameOriginListener()($this->event($request));
    }

    private function event(Request $request): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
