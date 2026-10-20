<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use ApiPlatform\Metadata\GetCollection;
use App\Api\Provider\LatestFileProvider;
use App\Domain\FileName;
use App\Entity\File;
use App\Repository\FileRepository;
use Symfony\Component\Uid\UuidV7;

final class FileRepositoryTest extends IntegrationTestCase
{
    public function testLatestIsEmptyWithoutFiles(): void
    {
        self::assertNull($this->service(FileRepository::class)->findLatest());
        self::assertSame([], $this->latestFromProvider());
    }

    public function testLatestBreaksASameSecondTieById(): void
    {
        $at = new \DateTimeImmutable('2026-10-18 12:00:00.100');
        $olderId = new UuidV7(UuidV7::generate($at));
        $newerId = new UuidV7(UuidV7::generate($at->modify('+500 milliseconds')));
        $this->persistFile('newer.txt', $at, $newerId);
        $this->persistFile('older.txt', $at, $olderId);
        $this->entityManager()->clear();

        self::assertEquals($newerId, $this->service(FileRepository::class)->findLatest()?->getId());
        $latest = $this->latestFromProvider();
        self::assertCount(1, $latest);
        self::assertEquals($newerId, $latest[0]->getId());
    }

    public function testLatestPrefersTheMostRecentUpload(): void
    {
        $recent = $this->persistFile('recent.txt', new \DateTimeImmutable('2026-10-18 12:00:00'));
        $this->persistFile('old.txt', new \DateTimeImmutable('2026-10-17 12:00:00'), new UuidV7(UuidV7::generate(new \DateTimeImmutable('2026-10-19 12:00:00'))));

        self::assertSame($recent, $this->service(FileRepository::class)->findLatest());
    }

    private function persistFile(string $name, \DateTimeImmutable $at, ?UuidV7 $id = null): File
    {
        $file = new File($id ?? new UuidV7(UuidV7::generate($at)), FileName::sanitize($name)->value(), 'text/plain', 1, $at);
        $this->entityManager()->persist($file);
        $this->entityManager()->flush();

        return $file;
    }

    /**
     * @return list<File>
     */
    private function latestFromProvider(): array
    {
        return $this->service(LatestFileProvider::class)->provide(new GetCollection());
    }
}
