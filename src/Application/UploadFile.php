<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exception\EmptyFile;
use App\Domain\Exception\FileTooLarge;
use App\Domain\Exception\NoFileProvided;
use App\Domain\Exception\UploadIncomplete;
use App\Domain\FileName;
use App\Entity\File;
use App\Storage\FileStorage;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\UuidV7;

final readonly class UploadFile
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileStorage $storage,
        private ClockInterface $clock,
        private int $maxBytes = File::MAX_BYTES,
    ) {
    }

    public function __invoke(?UploadedFile $file): File
    {
        $this->assertAcceptable($file);

        $now = $this->clock->now();
        $id = new UuidV7(UuidV7::generate($now));
        $name = FileName::sanitize($file->getClientOriginalName());
        $size = (int) $file->getSize();

        $this->storage->moveFrom($file->getPathname(), $id);

        try {
            $storedFile = new File($id, $name->value(), $this->storage->mimeTypeOf($id), $size, $now);
            $this->entityManager->persist($storedFile);
            $this->entityManager->flush();
        } catch (DbalException|ORMException $e) {
            $this->storage->delete($id);

            throw $e;
        }

        return $storedFile;
    }

    /**
     * @phpstan-assert UploadedFile $file
     */
    private function assertAcceptable(?UploadedFile $file): void
    {
        if (null === $file || \UPLOAD_ERR_NO_FILE === $file->getError()) {
            throw new NoFileProvided();
        }

        if (\in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)) {
            throw new FileTooLarge();
        }

        if (\UPLOAD_ERR_PARTIAL === $file->getError()) {
            throw new UploadIncomplete();
        }

        if (!$file->isValid()) {
            throw new UploadException($file->getErrorMessage());
        }

        if ($file->getSize() > $this->maxBytes) {
            throw new FileTooLarge();
        }

        if (0 === $file->getSize()) {
            throw new EmptyFile();
        }
    }
}
