<?php

declare(strict_types=1);

namespace App\Storage;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsFileStorageFactory
{
    public function __construct(
        public string $scheme,
    ) {
    }
}
