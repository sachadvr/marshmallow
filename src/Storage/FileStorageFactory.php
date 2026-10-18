<?php

declare(strict_types=1);

namespace App\Storage;

interface FileStorageFactory
{
    public const string TAG = 'marshmallow.storage_factory';

    public function create(Dsn $dsn): FileStorage;
}
