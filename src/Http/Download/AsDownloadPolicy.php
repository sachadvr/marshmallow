<?php

declare(strict_types=1);

namespace App\Http\Download;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsDownloadPolicy
{
    public function __construct(
        public int $priority = 0,
        public bool $fallback = false,
    ) {
    }
}
