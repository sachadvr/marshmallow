<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Text;
use App\Realtime\Topics;
use App\Tests\Support\FunctionalTestCase;
use App\Tests\Support\TextCollision;
use PHPUnit\Framework\Attributes\DataProvider;

final class TextApiTest extends FunctionalTestCase
{
    public function testGetReturnsTheInitialState(): void
    {
        $this->client->request('GET', '/api/text');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertJsonContains(['content' => '', 'version' => 1]);
        self::assertMatchesResourceItemJsonSchema(Text::class, format: 'json');
    }

    public function testAnUntrustedHostIsABadRequest(): void
    {
        $this->client->request('GET', 'http://rebound.example/api/text');

        self::assertResponseStatusCodeSame(400);
    }

    public function testPutReplacesTheTextBumpsTheVersionAndPublishesOnce(): void
    {
        $this->client->request('PUT', '/api/text', ['json' => ['content' => 'héllo', 'baseVersion' => 1]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['content' => 'héllo', 'version' => 2]);

        $updates = $this->updatesFor(Topics::TEXT);
        self::assertCount(1, $updates);
        self::assertSame('text', $updates[0]->getType());
        $payload = json_decode($updates[0]->getData(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame(['content' => 'héllo', 'version' => 2], array_intersect_key($payload, ['content' => true, 'version' => true]));
        self::assertFalse($updates[0]->isPrivate());

        $this->client->request('GET', '/api/text');
        self::assertJsonContains(['content' => 'héllo', 'version' => 2]);
    }

    public function testPutWithUnchangedContentNeitherBumpsNorPublishes(): void
    {
        $this->client->request('PUT', '/api/text', ['json' => ['content' => '', 'baseVersion' => 1]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['version' => 1]);
        self::assertSame([], $this->updatesFor(Topics::TEXT));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'empty' => [[]];

        yield 'no content' => [['baseVersion' => 1]];

        yield 'no baseVersion' => [['content' => 'x']];

        yield 'negative baseVersion' => [['content' => 'x', 'baseVersion' => -1]];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidBodies')]
    public function testPutWithAnInvalidBodyIsAnUnprocessableProblem(array $body): void
    {
        $this->client->request('PUT', '/api/text', ['json' => $body]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame([], $this->updatesFor(Topics::TEXT));
    }

    public function testPutOnAStaleVersionIsAConflictWithItsErrorCode(): void
    {
        $this->client->request('PUT', '/api/text', ['json' => ['content' => 'from the future', 'baseVersion' => 7]]);

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertJsonContains([
            'type' => 'urn:marshmallow:error:TEXT_CONFLICT',
            'title' => 'TEXT_CONFLICT',
            'status' => 409,
        ]);
        self::assertSame([], $this->updatesFor(Topics::TEXT));
    }

    public function testTheSecondOfTwoEditsOnTheSameVersionIsAConflict(): void
    {
        $this->client->request('PUT', '/api/text', ['json' => ['content' => 'alice', 'baseVersion' => 1]]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['content' => 'alice', 'version' => 2]);

        $this->client->request('PUT', '/api/text', ['json' => ['content' => 'bob', 'baseVersion' => 1]]);

        self::assertResponseStatusCodeSame(409);
        self::assertJsonContains(['type' => 'urn:marshmallow:error:TEXT_CONFLICT']);
        $this->client->request('GET', '/api/text');
        self::assertJsonContains(['content' => 'alice', 'version' => 2]);
        self::assertCount(1, $this->updatesFor(Topics::TEXT));
    }

    public function testPutWithInvalidJsonIsABadRequestProblem(): void
    {
        $this->client->request('PUT', '/api/text', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '{"content":',
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    public function testPutOneByteAboveTheLimitIsAViolationOnContent(): void
    {
        $this->client->request('PUT', '/api/text', ['json' => ['content' => str_repeat('a', Text::MAX_BYTES + 1), 'baseVersion' => 1]]);

        self::assertContentViolation();
        self::assertSame([], $this->updatesFor(Topics::TEXT));
    }

    public function testPutExactlyAtTheLimitIsAccepted(): void
    {
        $this->client->request('PUT', '/api/text', ['json' => ['content' => str_repeat('a', Text::MAX_BYTES), 'baseVersion' => 1]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['version' => 2]);
    }

    public function testTheLimitCountsBytesNotCharacters(): void
    {
        $atLimit = str_repeat('é', Text::MAX_BYTES / 2);

        $this->client->request('PUT', '/api/text', ['json' => ['content' => $atLimit, 'baseVersion' => 1]]);
        self::assertResponseIsSuccessful();

        $this->client->request('PUT', '/api/text', ['json' => ['content' => $atLimit.'a', 'baseVersion' => 2]]);
        self::assertContentViolation();
        $this->client->request('GET', '/api/text');
        self::assertJsonContains(['version' => 2]);
        self::assertCount(1, $this->updatesFor(Topics::TEXT));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function foreignRequests(): iterable
    {
        yield 'Sec-Fetch-Site cross-site' => [['Sec-Fetch-Site' => 'cross-site']];

        yield 'foreign Origin' => [['Origin' => 'https://evil.example']];

        yield 'foreign port' => [['Origin' => 'http://localhost:8081']];

        yield 'foreign scheme' => [['Origin' => 'https://localhost']];

        yield 'opaque Origin' => [['Origin' => 'null']];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('foreignRequests')]
    public function testPutFromAnotherSiteIsForbidden(array $headers): void
    {
        $this->client->request('PUT', '/api/text', ['json' => ['content' => 'x', 'baseVersion' => 1], 'headers' => $headers]);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame([], $this->updatesFor(Topics::TEXT));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function sameOriginRequests(): iterable
    {
        yield 'no browser headers (curl, scripts)' => [[]];

        yield 'same Origin' => [['Origin' => 'http://localhost']];

        yield 'Sec-Fetch-Site same-origin' => [['Sec-Fetch-Site' => 'same-origin', 'Origin' => 'http://localhost']];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('sameOriginRequests')]
    public function testPutFromTheSameOriginIsAccepted(array $headers): void
    {
        $this->client->request('PUT', '/api/text', ['json' => ['content' => 'x', 'baseVersion' => 1], 'headers' => $headers]);

        self::assertResponseIsSuccessful();
    }

    public function testReadsAreNotSubjectToTheOriginCheck(): void
    {
        $this->client->request('GET', '/api/text', ['headers' => ['Sec-Fetch-Site' => 'cross-site']]);

        self::assertResponseIsSuccessful();
    }

    public function testAVersionCollisionIsAConflictAndTheWorkerRecovers(): void
    {
        $this->service(TextCollision::class)->arm();

        $this->client->request('PUT', '/api/text', ['json' => ['content' => 'lost', 'baseVersion' => 1]]);

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame([], $this->updatesFor(Topics::TEXT));

        $this->client->request('PUT', '/api/text', ['json' => ['content' => 'retried', 'baseVersion' => 2]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['content' => 'retried', 'version' => 3]);
    }

    private function assertContentViolation(): void
    {
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        $violations = $this->client->getResponse()?->toArray(false)['violations'] ?? null;
        self::assertIsArray($violations);
        self::assertSame(['content'], array_column($violations, 'propertyPath'));
    }
}
