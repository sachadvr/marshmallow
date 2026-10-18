<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exception\FileNotFound;
use App\Repository\FileRepository;
use App\Storage\FileStorage;
use Symfony\Component\Uid\Uuid;

final readonly class GetFile
{
    public function __construct(
        private FileRepository $files,
        private FileStorage $storage,
    ) {
    }

    public function __invoke(string $id): FileDownload
    {
        if (!Uuid::isValid($id)) {
            throw new FileNotFound();
        }

        $file = $this->files->find(Uuid::fromString($id)) ?? throw new FileNotFound();
        $path = $this->storage->pathFor($file->getId());

        if (!is_file($path)) {
            throw new FileNotFound();
        }

        return new FileDownload($file, $path);
    }
}
