<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Download;

use App\Domain\FileName;
use App\Entity\File;
use App\Http\Download\FallbackPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class FallbackPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function types(): iterable
    {
        yield 'pdf' => ['application/pdf', 'application/pdf'];

        yield 'text' => ['text/plain', 'text/plain; charset=utf-8'];

        yield 'csv' => ['text/csv', 'text/csv; charset=utf-8'];

        yield 'json' => ['application/json', 'application/json'];

        yield 'zip' => ['application/zip', 'application/zip'];

        yield 'mp3' => ['audio/mpeg', 'audio/mpeg'];

        yield 'mp4' => ['video/mp4', 'video/mp4'];

        yield 'unknown' => ['application/x-msdownload', 'application/octet-stream'];

        yield 'raster image' => ['image/png', 'application/octet-stream'];
    }

    #[DataProvider('types')]
    public function testSupportsEverythingAsAnAttachmentKeepingOnlyAllowlistedTypes(string $mimeType, string $contentType): void
    {
        $policy = new FallbackPolicy();
        $file = $this->file($mimeType);

        self::assertTrue($policy->supports($file));
        self::assertSame($contentType, $policy->disposition($file)->contentType);
        self::assertFalse($policy->disposition($file)->inline);
    }

    private function file(string $mimeType): File
    {
        return new File(Uuid::v7(), FileName::sanitize('document')->value(), $mimeType, 1, new \DateTimeImmutable());
    }
}
