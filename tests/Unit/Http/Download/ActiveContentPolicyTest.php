<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Download;

use App\Domain\FileName;
use App\Entity\File;
use App\Http\Download\ActiveContentPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ActiveContentPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function activeTypes(): iterable
    {
        yield 'svg' => ['image/svg+xml'];

        yield 'html' => ['text/html'];

        yield 'xhtml' => ['application/xhtml+xml'];

        yield 'xml' => ['text/xml'];

        yield 'xml application' => ['application/xml'];

        yield 'javascript' => ['text/javascript'];

        yield 'javascript application' => ['application/javascript'];
    }

    #[DataProvider('activeTypes')]
    public function testActiveTypesAreOpaqueAttachments(string $mimeType): void
    {
        $policy = new ActiveContentPolicy();
        $file = $this->file($mimeType);

        self::assertTrue($policy->supports($file));
        self::assertSame('application/octet-stream', $policy->disposition($file)->contentType);
        self::assertFalse($policy->disposition($file)->inline);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherTypes(): iterable
    {
        yield 'png' => ['image/png'];

        yield 'plain text' => ['text/plain'];

        yield 'pdf' => ['application/pdf'];
    }

    #[DataProvider('otherTypes')]
    public function testIgnoresOtherTypes(string $mimeType): void
    {
        self::assertFalse(new ActiveContentPolicy()->supports($this->file($mimeType)));
    }

    private function file(string $mimeType): File
    {
        return new File(Uuid::v7(), FileName::sanitize('payload.png')->value(), $mimeType, 1, new \DateTimeImmutable());
    }
}
