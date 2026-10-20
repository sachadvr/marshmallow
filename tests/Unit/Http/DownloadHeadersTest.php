<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Domain\FileName;
use App\Entity\File;
use App\Http\Download\ActiveContentPolicy;
use App\Http\Download\DownloadDisposition;
use App\Http\Download\DownloadPolicy;
use App\Http\Download\FallbackPolicy;
use App\Http\Download\RasterImagePolicy;
use App\Http\DownloadHeaders;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class DownloadHeadersTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function rasterImages(): iterable
    {
        foreach (RasterImagePolicy::TYPES as $mimeType) {
            yield $mimeType => [$mimeType];
        }
    }

    #[DataProvider('rasterImages')]
    public function testRasterImagesAreInlineWithTheirDetectedType(string $mimeType): void
    {
        $headers = self::headers()->for($this->file('photo.bin', $mimeType));

        self::assertSame($mimeType, $headers['Content-Type']);
        self::assertSame('inline; filename=photo.bin', $headers['Content-Disposition']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function activeOrUnknownTypes(): iterable
    {
        yield 'svg' => ['image/svg+xml'];

        yield 'html' => ['text/html'];

        yield 'javascript' => ['text/javascript'];

        yield 'xml' => ['text/xml'];

        yield 'unknown' => ['application/x-msdownload'];
    }

    #[DataProvider('activeOrUnknownTypes')]
    public function testActiveOrUnknownTypesAreOctetStreamAttachments(string $mimeType): void
    {
        $headers = self::headers()->for($this->file('payload.png', $mimeType));

        self::assertSame('application/octet-stream', $headers['Content-Type']);
        self::assertSame('attachment; filename=payload.png', $headers['Content-Disposition']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function passiveTypes(): iterable
    {
        yield 'pdf' => ['application/pdf', 'application/pdf'];

        yield 'text' => ['text/plain', 'text/plain; charset=utf-8'];

        yield 'csv' => ['text/csv', 'text/csv; charset=utf-8'];

        yield 'json' => ['application/json', 'application/json'];

        yield 'zip' => ['application/zip', 'application/zip'];

        yield 'mp3' => ['audio/mpeg', 'audio/mpeg'];

        yield 'mp4' => ['video/mp4', 'video/mp4'];
    }

    #[DataProvider('passiveTypes')]
    public function testPassiveTypesKeepTheirTypeButAreAttachments(string $mimeType, string $contentType): void
    {
        $headers = self::headers()->for($this->file('document', $mimeType));

        self::assertSame($contentType, $headers['Content-Type']);
        self::assertSame('attachment; filename=document', $headers['Content-Disposition']);
    }

    public function testAlwaysSetsNosniffSandboxAndPrivateCaching(): void
    {
        $headers = self::headers()->for($this->file('a.png', 'image/png'));

        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
        self::assertSame("default-src 'none'; sandbox", $headers['Content-Security-Policy']);
        self::assertSame('private, max-age=86400', $headers['Cache-Control']);
    }

    public function testNonAsciiNamesGetAnRfc5987FilenameAndAnAsciiFallback(): void
    {
        $headers = self::headers()->for($this->file("l'été (1).pdf", 'application/pdf'));

        self::assertSame(
            "attachment; filename=\"l'_t_ (1).pdf\"; filename*=utf-8''l%27%C3%A9t%C3%A9%20%281%29.pdf",
            $headers['Content-Disposition'],
        );
    }

    public function testPercentSignsAreReplacedInTheFallback(): void
    {
        $headers = self::headers()->for($this->file('100%.txt', 'text/plain'));

        self::assertSame("attachment; filename=100_.txt; filename*=utf-8''100%25.txt", $headers['Content-Disposition']);
    }

    public function testTheFirstSupportingPolicyWins(): void
    {
        $headers = new DownloadHeaders([
            $this->policy(supports: false, disposition: new DownloadDisposition('text/never', inline: true)),
            $this->policy(supports: true, disposition: new DownloadDisposition('text/first', inline: true)),
            $this->policy(supports: true, disposition: new DownloadDisposition('text/second', inline: false)),
        ]);

        $result = $headers->for($this->file('a.txt', 'text/plain'));

        self::assertSame('text/first', $result['Content-Type']);
        self::assertSame('inline; filename=a.txt', $result['Content-Disposition']);
    }

    public function testFailsWhenNoPolicySupportsTheFile(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No download policy supports "text/plain".');

        new DownloadHeaders([])->for($this->file('a.txt', 'text/plain'));
    }

    private static function headers(): DownloadHeaders
    {
        return new DownloadHeaders([new ActiveContentPolicy(), new RasterImagePolicy(), new FallbackPolicy()]);
    }

    private function policy(bool $supports, DownloadDisposition $disposition): DownloadPolicy
    {
        return new readonly class($supports, $disposition) implements DownloadPolicy {
            public function __construct(private bool $supports, private DownloadDisposition $disposition)
            {
            }

            public function supports(File $file): bool
            {
                return $this->supports;
            }

            public function disposition(File $file): DownloadDisposition
            {
                return $this->disposition;
            }
        };
    }

    private function file(string $name, string $mimeType): File
    {
        return new File(Uuid::v7(), FileName::sanitize($name)->value(), $mimeType, 1, new \DateTimeImmutable());
    }
}
