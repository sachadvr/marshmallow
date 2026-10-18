<?php

declare(strict_types=1);

namespace App\Http\Download;

use App\Entity\File;

#[AsDownloadPolicy]
final readonly class RasterImagePolicy implements DownloadPolicy
{
    public const array TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public function supports(File $file): bool
    {
        return \in_array($file->getMimeType(), self::TYPES, true);
    }

    public function disposition(File $file): DownloadDisposition
    {
        return new DownloadDisposition($file->getMimeType(), inline: true);
    }
}
