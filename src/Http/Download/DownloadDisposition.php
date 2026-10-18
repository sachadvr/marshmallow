<?php

declare(strict_types=1);

namespace App\Http\Download;

final readonly class DownloadDisposition
{
    public const string OPAQUE_TYPE = 'application/octet-stream';

    public function __construct(
        public string $contentType,
        public bool $inline,
    ) {
    }
}
