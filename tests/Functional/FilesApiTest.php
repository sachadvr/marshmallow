<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\File;
use App\Realtime\Topics;
use App\Tests\Support\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

final class FilesApiTest extends FunctionalTestCase
{
    private const string PNG = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x06\0\0\0\x1f\x15\xc4\x89\0\0\0\rIDATx\x9cc\0\x01\0\0\x05\0\x01\x0d\x0a\x2d\xb4\0\0\0\0IEND\xaeB`\x82";

    public function testLatestIsEmptyBeforeAnyUpload(): void
    {
        $this->client->request('GET', '/api/files/latest');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->client->getResponse()?->toArray());
    }

    public function testUploadStoresTheFileAndPublishesIt(): void
    {
        $this->client->request('POST', '/api/files', $this->upload($this->uploadedFile('pixel.png', self::PNG)));

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertMatchesResourceItemJsonSchema(File::class, format: 'json');
        $file = $this->storedFileJson();
        self::assertSame('pixel.png', $file['name']);
        self::assertSame('image/png', $file['mimeType']);
        self::assertSame(\strlen(self::PNG), $file['size']);
        self::assertTrue($file['inlineImage']);
        self::assertSame('/api/files/'.$file['id'].'/content', $file['url']);

        $updates = $this->updatesFor(Topics::FILES);
        self::assertCount(1, $updates);
        self::assertSame('file', $updates[0]->getType());
        self::assertSame($file, json_decode($updates[0]->getData(), true, flags: \JSON_THROW_ON_ERROR));

        $this->client->request('GET', '/api/files/latest');
        self::assertSame([$file], $this->responseJson());

        $this->client->request('GET', '/api/files/'.$file['id']);
        self::assertResponseIsSuccessful();
        self::assertSame($file, $this->responseJson());
    }

    public function testUploadWithoutFileIsRefused(): void
    {
        $this->client->request('POST', '/api/files', ['headers' => ['Content-Type' => 'multipart/form-data']]);

        self::assertProblem(400, 'NO_FILE');
    }

    public function testEmptyUploadIsRefused(): void
    {
        $this->client->request('POST', '/api/files', $this->upload($this->uploadedFile('empty.txt', '')));

        self::assertProblem(400, 'EMPTY_FILE');
    }

    public function testUploadAbovePhpLimitIsTooLarge(): void
    {
        $this->client->request('POST', '/api/files', $this->upload($this->uploadedFile('big.bin', 'x', \UPLOAD_ERR_INI_SIZE)));

        self::assertProblem(413, 'FILE_TOO_LARGE');
        self::assertSame([], $this->updatesFor(Topics::FILES));
    }

    public function testTruncatedUploadIsIncomplete(): void
    {
        $this->client->request('POST', '/api/files', $this->upload($this->uploadedFile('cut.bin', 'x', \UPLOAD_ERR_PARTIAL)));

        self::assertProblem(400, 'UPLOAD_INCOMPLETE');
        self::assertSame([], $this->updatesFor(Topics::FILES));
    }

    public function testSameNameTwiceKeepsBothFiles(): void
    {
        $first = $this->uploadFile('notes.txt', 'first');
        $second = $this->uploadFile('notes.txt', 'second');

        self::assertNotSame($first['id'], $second['id']);
        self::assertSame('notes.txt', $second['name']);

        $this->client->request('GET', $first['url']);
        self::assertSame('first', $this->downloadedContent());
        $this->client->request('GET', $second['url']);
        self::assertSame('second', $this->downloadedContent());

        $this->client->request('GET', '/api/files/latest');
        self::assertSame([$second], $this->responseJson());
    }

    public function testCrossOriginUploadIsForbidden(): void
    {
        $this->client->request('POST', '/api/files', $this->upload(
            $this->uploadedFile('pixel.png', self::PNG),
            ['Origin' => 'https://evil.example'],
        ));

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame([], $this->updatesFor(Topics::FILES));
        self::assertSame([], glob($this->workDir.'/files/*'));
    }

    public function testUnknownFileIsNotFound(): void
    {
        $this->client->request('GET', '/api/files/'.Uuid::v7()->toRfc4122());

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    public function testMalformedFileIdIsNotFound(): void
    {
        $this->client->request('GET', '/api/files/not-a-uuid');

        self::assertResponseStatusCodeSame(404);
    }

    public function testRasterImageIsServedInline(): void
    {
        $file = $this->uploadFile('pixel.png', self::PNG);

        $this->client->request('GET', $file['url']);

        self::assertResponseIsSuccessful();
        self::assertSame(self::PNG, $this->downloadedContent());
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertResponseHeaderSame('Content-Disposition', 'inline; filename=pixel.png');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Content-Security-Policy', "default-src 'none'; sandbox");
        self::assertResponseHeaderSame('Cache-Control', 'max-age=86400, private');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function activeContent(): iterable
    {
        yield 'svg' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'];

        yield 'html' => ['page.html', '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>'];
    }

    #[DataProvider('activeContent')]
    public function testActiveContentIsAnOctetStreamAttachment(string $name, string $content): void
    {
        $file = $this->uploadFile($name, $content);
        self::assertFalse($file['inlineImage']);

        $this->client->request('GET', $file['url']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/octet-stream');
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename='.$name);
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Content-Security-Policy', "default-src 'none'; sandbox");
    }

    public function testNonAsciiNameGetsAnRfc5987Filename(): void
    {
        $file = $this->uploadFile("l'été (1).txt", 'contenu');

        $this->client->request('GET', $file['url']);

        self::assertResponseHeaderSame(
            'Content-Disposition',
            "attachment; filename=\"l'_t_ (1).txt\"; filename*=utf-8''l%27%C3%A9t%C3%A9%20%281%29.txt",
        );
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=utf-8');
    }

    public function testRangeRequestReturnsPartialContent(): void
    {
        $file = $this->uploadFile('alphabet.txt', 'abcdefghij');

        $this->client->request('GET', $file['url'], ['headers' => ['Range' => 'bytes=2-5']]);

        self::assertResponseStatusCodeSame(206);
        self::assertResponseHeaderSame('Content-Range', 'bytes 2-5/10');
        self::assertSame('cdef', $this->downloadedContent());
    }

    public function testContentOfUnknownFileIsANotFoundProblem(): void
    {
        $this->client->request('GET', '/api/files/'.Uuid::v7()->toRfc4122().'/content');

        self::assertProblem(404, 'NOT_FOUND');
    }

    public function testContentMissingOnDiskIsANotFoundProblem(): void
    {
        $file = $this->uploadFile('gone.txt', 'gone');
        unlink($this->workDir.'/files/'.$file['id']);

        $this->client->request('GET', $file['url']);

        self::assertProblem(404, 'NOT_FOUND');
    }

    public function testContentWithMalformedIdIsNotFound(): void
    {
        $this->client->request('GET', '/api/files/not-a-uuid/content');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    private static function assertProblem(int $status, string $code): void
    {
        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertJsonContains(['type' => 'urn:marshmallow:error:'.$code, 'title' => $code, 'status' => $status]);
    }

    /**
     * @return array{id: string, name: string, mimeType: string, size: int, uploadedAt: string, inlineImage: bool, url: string}
     */
    private function uploadFile(string $name, string $content): array
    {
        $this->client->request('POST', '/api/files', $this->upload($this->uploadedFile($name, $content)));
        self::assertResponseStatusCodeSame(201);

        return $this->storedFileJson();
    }

    /**
     * @return array{id: string, name: string, mimeType: string, size: int, uploadedAt: string, inlineImage: bool, url: string}
     */
    private function storedFileJson(): array
    {
        /** @var array{id: string, name: string, mimeType: string, size: int, uploadedAt: string, inlineImage: bool, url: string} $file */
        $file = $this->responseJson();

        return $file;
    }

    private function downloadedContent(): string
    {
        return $this->client->getKernelBrowser()->getInternalResponse()->getContent();
    }

    /**
     * @return array<mixed>
     */
    private function responseJson(): array
    {
        return $this->client->getResponse()?->toArray() ?? [];
    }
}
