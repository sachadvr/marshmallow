<?php

declare(strict_types=1);

namespace App\Http\Download;

use App\Entity\File;

interface DownloadPolicy
{
    public const string TAG = 'marshmallow.download_policy';

    public function supports(File $file): bool;

    public function disposition(File $file): DownloadDisposition;
}
