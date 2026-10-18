<?php

declare(strict_types=1);

namespace App\Http\Download;

use App\Entity\File;

#[AsDownloadPolicy(priority: 100)]
final readonly class ActiveContentPolicy implements DownloadPolicy
{
    private const array ACTIVE_TYPES = [
        'text/html',
        'application/xhtml+xml',
        'image/svg+xml',
        'text/xml',
        'application/xml',
        'text/javascript',
        'application/javascript',
        'application/x-javascript',
    ];

    public function supports(File $file): bool
    {
        return \in_array($file->getMimeType(), self::ACTIVE_TYPES, true);
    }

    public function disposition(File $file): DownloadDisposition
    {
        return new DownloadDisposition(DownloadDisposition::OPAQUE_TYPE, inline: false);
    }
}
