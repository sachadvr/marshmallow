<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\UploadFile;
use App\Entity\File;
use App\Repository\FileRepository;
use App\Tests\Support\FailingPostFlush;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadFileTest extends IntegrationTestCase
{
    public function testStoresTheBytesUnderFilesDirAndTheRowInTheDatabase(): void
    {
        $storedFile = $this->service(UploadFile::class)($this->upload('hello world', 'notes.txt'));
        $this->entityManager()->clear();

        self::assertStringEqualsFile($this->filesDir.'/'.$storedFile->getId()->toRfc4122(), 'hello world');
        $reloaded = $this->service(FileRepository::class)->find($storedFile->getId());
        self::assertInstanceOf(File::class, $reloaded);
        self::assertSame('notes.txt', $reloaded->getOriginalName());
        self::assertSame('text/plain', $reloaded->getMimeType());
        self::assertSame(11, $reloaded->getSize());
    }

    public function testTheSameNameTwiceCreatesTwoFiles(): void
    {
        $upload = $this->service(UploadFile::class);

        $first = $upload($this->upload('one', 'same.txt'));
        $second = $upload($this->upload('two', 'same.txt'));

        self::assertNotEquals($first->getId(), $second->getId());
        self::assertSame(2, $this->service(FileRepository::class)->count([]));
        self::assertStringEqualsFile($this->filesDir.'/'.$first->getId()->toRfc4122(), 'one');
    }

    public function testRemovesTheStoredBytesWhenTheFlushFails(): void
    {
        $this->entityManager()->getConnection()->executeStatement('DROP TABLE file');

        try {
            $this->service(UploadFile::class)($this->upload('orphan?', 'a.txt'));
            self::fail('The flush failure must propagate.');
        } catch (TableNotFoundException) {
        }

        self::assertSame([], glob($this->filesDir.'/*'));
    }

    public function testKeepsTheStoredBytesWhenAPostFlushListenerFailsAfterTheCommit(): void
    {
        $this->service(FailingPostFlush::class)->arm();

        try {
            $this->service(UploadFile::class)($this->upload('committed', 'a.txt'));
            self::fail('The postFlush failure must propagate.');
        } catch (\RuntimeException $e) {
            self::assertSame('postFlush failed', $e->getMessage());
        }

        $rows = $this->entityManager()->getConnection()->fetchFirstColumn('SELECT id FROM file');
        self::assertCount(1, $rows);
        $files = glob($this->filesDir.'/*') ?: [];
        self::assertCount(1, $files);
        self::assertStringEqualsFile($files[0], 'committed');
    }

    private function upload(string $content, string $clientName): UploadedFile
    {
        $path = $this->filesDir.'-tmp/'.bin2hex(random_bytes(4));
        new Filesystem()->dumpFile($path, $content);

        return new UploadedFile($path, $clientName, null, \UPLOAD_ERR_OK, true);
    }
}
