<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\UploadFile;
use App\Domain\Exception\EmptyFile;
use App\Domain\Exception\FileTooLarge;
use App\Domain\Exception\NoFileProvided;
use App\Domain\Exception\UploadIncomplete;
use App\Storage\LocalFileStorage;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\UuidV7;

final class UploadFileTest extends TestCase
{
    private const string PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private string $workDir;
    private string $filesDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/marshmallow-unit-'.bin2hex(random_bytes(6));
        $this->filesDir = $this->workDir.'/files';
        new Filesystem()->mkdir($this->workDir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->workDir);
    }

    public function testRejectsAMissingFile(): void
    {
        $this->expectException(NoFileProvided::class);
        $this->uploadFile()(null);
    }

    #[TestWith([\UPLOAD_ERR_INI_SIZE])]
    #[TestWith([\UPLOAD_ERR_FORM_SIZE])]
    public function testRejectsAFileTruncatedByPhpLimits(int $error): void
    {
        $this->expectException(FileTooLarge::class);
        $this->uploadFile()(new UploadedFile($this->workDir.'/missing', 'big.bin', null, $error, true));
    }

    public function testRejectsATruncatedUploadAsIncomplete(): void
    {
        $this->expectException(UploadIncomplete::class);
        $this->uploadFile()(new UploadedFile($this->workDir.'/missing', 'cut.bin', null, \UPLOAD_ERR_PARTIAL, true));
    }

    #[TestWith([\UPLOAD_ERR_NO_TMP_DIR])]
    #[TestWith([\UPLOAD_ERR_CANT_WRITE])]
    public function testServerSideUploadFailuresStayServerErrors(int $error): void
    {
        $this->expectException(UploadException::class);
        $this->uploadFile()(new UploadedFile($this->workDir.'/missing', 'a.bin', null, $error, true));
    }

    public function testRejectsAFileOverTheConfiguredMaximum(): void
    {
        $this->expectException(FileTooLarge::class);
        $this->uploadFile(maxBytes: 3)($this->upload('abcd', 'four.txt'));
    }

    public function testAcceptsAFileOfExactlyTheConfiguredMaximum(): void
    {
        self::assertSame(3, $this->uploadFile(maxBytes: 3)($this->upload('abc', 'three.txt'))->getSize());
    }

    public function testRejectsAnEmptyFile(): void
    {
        $this->expectException(EmptyFile::class);
        $this->uploadFile()($this->upload('', 'empty.txt'));
    }

    public function testStoresTheFileUnderItsIdWithServerSideMetadata(): void
    {
        $clock = new MockClock('2026-10-18 12:00:00');
        $upload = $this->upload((string) base64_decode(self::PNG_BASE64, true), '../evil.png', 'text/html');

        $storedFile = $this->uploadFile(clock: $clock)($upload);

        self::assertSame('evil.png', $storedFile->getOriginalName());
        self::assertSame('image/png', $storedFile->getMimeType());
        self::assertEquals($clock->now(), $storedFile->getUploadedAt());
        self::assertInstanceOf(UuidV7::class, $storedFile->getId());
        self::assertEquals($clock->now(), $storedFile->getId()->getDateTime());
        self::assertFileExists($this->filesDir.'/'.$storedFile->getId()->toRfc4122());
        self::assertFileDoesNotExist($upload->getPathname());
    }

    public function testDeletesTheFileWhenTheFlushFails(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('flush')->willThrowException(new class('database down') extends \RuntimeException implements DbalException {});

        try {
            $this->uploadFile(entityManager: $entityManager)($this->upload('data', 'a.txt'));
            self::fail('The flush failure must propagate.');
        } catch (DbalException $e) {
            self::assertSame('database down', $e->getMessage());
        }

        self::assertSame([], glob($this->filesDir.'/*'));
    }

    public function testKeepsTheFileWhenTheFlushFailsAfterTheCommit(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('flush')->willThrowException(new \RuntimeException('postFlush listener failed'));

        try {
            $this->uploadFile(entityManager: $entityManager)($this->upload('data', 'a.txt'));
            self::fail('The post-commit failure must propagate.');
        } catch (\RuntimeException $e) {
            self::assertSame('postFlush listener failed', $e->getMessage());
        }

        self::assertCount(1, glob($this->filesDir.'/*') ?: []);
    }

    private function uploadFile(int $maxBytes = 1024, ?MockClock $clock = null, ?EntityManagerInterface $entityManager = null): UploadFile
    {
        return new UploadFile(
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            new LocalFileStorage($this->filesDir),
            $clock ?? new MockClock(),
            $maxBytes,
        );
    }

    private function upload(string $content, string $clientName, ?string $clientMimeType = null): UploadedFile
    {
        $path = $this->workDir.'/'.bin2hex(random_bytes(4));
        file_put_contents($path, $content);

        return new UploadedFile($path, $clientName, $clientMimeType, \UPLOAD_ERR_OK, true);
    }
}
