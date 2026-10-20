<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\Entity\File;
use App\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Uid\Uuid;

final class FileNormalizerTest extends IntegrationTestCase
{
    private const string ID = '0199f8a0-0000-7000-8000-000000000001';

    #[TestWith(['image/png', true])]
    #[TestWith(['image/svg+xml', false])]
    #[TestWith(['application/pdf', false])]
    public function testAddsTheDownloadUrlAndTheInlineFlag(string $mimeType, bool $inline): void
    {
        $normalized = $this->normalize($this->file($mimeType), ['groups' => ['file:read']]);

        self::assertSame([
            'id' => self::ID,
            'name' => 'a.bin',
            'mimeType' => $mimeType,
            'size' => 3,
            'uploadedAt' => '2026-10-18T12:00:00+00:00',
            'inlineImage' => $inline,
            'url' => '/api/files/'.self::ID.'/content',
        ], $normalized);
    }

    public function testLeavesOtherGroupsUntouched(): void
    {
        $normalized = $this->normalize($this->file('image/png'), ['groups' => ['other']]);

        self::assertArrayNotHasKey('url', $normalized);
        self::assertArrayNotHasKey('inlineImage', $normalized);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<mixed>
     */
    private function normalize(File $file, array $context): array
    {
        $normalized = $this->service(NormalizerInterface::class)->normalize($file, 'json', $context);
        self::assertIsArray($normalized);

        return $normalized;
    }

    private function file(string $mimeType): File
    {
        return new File(Uuid::fromString(self::ID), 'a.bin', $mimeType, 3, new \DateTimeImmutable('2026-10-18 12:00:00+00:00'));
    }
}
