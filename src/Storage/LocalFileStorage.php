<?php

declare(strict_types=1);

namespace App\Storage;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;

#[Exclude]
final readonly class LocalFileStorage implements FileStorage
{
    private const string FALLBACK_MIME_TYPE = 'application/octet-stream';

    private Filesystem $filesystem;

    public function __construct(
        private string $filesDir,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function moveFrom(string $path, Uuid $key): void
    {
        $this->filesystem->mkdir($this->filesDir);
        $this->filesystem->rename($path, $this->pathFor($key));
    }

    public function mimeTypeOf(Uuid $key): string
    {
        return new \finfo(\FILEINFO_MIME_TYPE)->file($this->pathFor($key)) ?: self::FALLBACK_MIME_TYPE;
    }

    public function pathFor(Uuid $key): string
    {
        return $this->filesDir.'/'.$key->toRfc4122();
    }

    public function delete(Uuid $key): void
    {
        $this->filesystem->remove($this->pathFor($key));
    }
}
