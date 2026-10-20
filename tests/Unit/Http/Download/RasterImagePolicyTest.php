<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Download;

use App\Domain\FileName;
use App\Entity\File;
use App\Http\Download\RasterImagePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RasterImagePolicyTest extends TestCase
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
        $policy = new RasterImagePolicy();
        $file = $this->file($mimeType);

        self::assertTrue($policy->supports($file));
        self::assertSame($mimeType, $policy->disposition($file)->contentType);
        self::assertTrue($policy->disposition($file)->inline);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherTypes(): iterable
    {
        yield 'svg' => ['image/svg+xml'];

        yield 'bmp' => ['image/bmp'];

        yield 'pdf' => ['application/pdf'];
    }

    #[DataProvider('otherTypes')]
    public function testIgnoresEverythingElse(string $mimeType): void
    {
        self::assertFalse(new RasterImagePolicy()->supports($this->file($mimeType)));
    }

    private function file(string $mimeType): File
    {
        return new File(Uuid::v7(), FileName::sanitize('photo.bin')->value(), $mimeType, 1, new \DateTimeImmutable());
    }
}
