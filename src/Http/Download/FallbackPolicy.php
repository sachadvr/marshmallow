<?php

declare(strict_types=1);

namespace App\Http\Download;

use App\Entity\File;

#[AsDownloadPolicy(priority: -100, fallback: true)]
final readonly class FallbackPolicy implements DownloadPolicy
{
    private const array ALLOWED_TYPES = [
        'application/pdf' => 'application/pdf',
        'text/plain' => 'text/plain; charset=utf-8',
        'text/csv' => 'text/csv; charset=utf-8',
        'application/json' => 'application/json',
        'application/zip' => 'application/zip',
        'audio/mpeg' => 'audio/mpeg',
        'video/mp4' => 'video/mp4',
    ];

    public function supports(File $file): bool
    {
        return true;
    }

    public function disposition(File $file): DownloadDisposition
    {
        return new DownloadDisposition(self::ALLOWED_TYPES[$file->getMimeType()] ?? DownloadDisposition::OPAQUE_TYPE, inline: false);
    }
}
