<?php

declare(strict_types=1);

namespace App\Application;

use App\Entity\File;

final readonly class FileDownload
{
    public function __construct(
        public File $file,
        public string $path,
    ) {
    }
}
