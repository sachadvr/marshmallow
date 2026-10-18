<?php

declare(strict_types=1);

namespace App\Storage;

#[AsFileStorageFactory(scheme: 'local')]
final readonly class LocalFileStorageFactory implements FileStorageFactory
{
    public function create(Dsn $dsn): FileStorage
    {
        if ('' !== $dsn->host || '' === $dsn->path) {
            throw new \InvalidArgumentException('The local file storage DSN must be "local:///absolute/path".');
        }

        return new LocalFileStorage($dsn->path);
    }
}
