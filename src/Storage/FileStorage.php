<?php

declare(strict_types=1);

namespace App\Storage;

use Symfony\Component\Uid\Uuid;

interface FileStorage
{
    public function moveFrom(string $path, Uuid $key): void;

    public function mimeTypeOf(Uuid $key): string;

    public function pathFor(Uuid $key): string;

    public function delete(Uuid $key): void;
}
