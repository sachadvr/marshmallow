<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\GetFile;
use App\Domain\Exception\FileNotFound;
use App\Domain\FileName;
use App\Entity\File;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;

final class GetFileTest extends IntegrationTestCase
{
    public function testReturnsTheFileAndItsPath(): void
    {
        $file = $this->persistFile();
        new Filesystem()->dumpFile($this->filesDir.'/'.$file->getId()->toRfc4122(), 'x');

        $download = $this->service(GetFile::class)($file->getId()->toRfc4122());

        self::assertSame($file, $download->file);
        self::assertSame($this->filesDir.'/'.$file->getId()->toRfc4122(), $download->path);
    }

    #[TestWith(['not-a-uuid'])]
    #[TestWith([''])]
    #[TestWith(['0199f8a0-0000-7000-8000-000000000001'])]
    public function testRejectsAnUnknownId(string $id): void
    {
        $this->expectException(FileNotFound::class);
        $this->service(GetFile::class)($id);
    }

    public function testRejectsAFileMissingFromDisk(): void
    {
        $file = $this->persistFile();

        $this->expectException(FileNotFound::class);
        $this->service(GetFile::class)($file->getId()->toRfc4122());
    }

    private function persistFile(): File
    {
        $file = new File(Uuid::v7(), FileName::sanitize('a.txt')->value(), 'text/plain', 1, new \DateTimeImmutable());
        $this->entityManager()->persist($file);
        $this->entityManager()->flush();

        return $file;
    }
}
