<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\Download\ActiveContentPolicy;
use App\Http\Download\FallbackPolicy;
use App\Http\Download\RasterImagePolicy;
use App\Http\DownloadHeaders;
use App\Storage\FileStorage;
use App\Storage\LocalFileStorage;
use Symfony\Component\Uid\Uuid;

final class ContainerWiringTest extends IntegrationTestCase
{
    public function testDownloadHeadersGetsThePoliciesByDescendingPriority(): void
    {
        $policies = new \ReflectionProperty(DownloadHeaders::class, 'policies')->getValue($this->service(DownloadHeaders::class));
        self::assertIsIterable($policies);

        $classes = [];

        foreach ($policies as $policy) {
            self::assertIsObject($policy);
            $classes[] = $policy::class;
        }

        self::assertSame([ActiveContentPolicy::class, RasterImagePolicy::class, FallbackPolicy::class], $classes);
    }

    public function testTheStorageDefaultsToALocalDsnBuiltFromFilesDir(): void
    {
        $storage = $this->service(FileStorage::class);
        $key = Uuid::v7();

        self::assertInstanceOf(LocalFileStorage::class, $storage);
        self::assertSame($this->filesDir.'/'.$key->toRfc4122(), $storage->pathFor($key));
    }

    public function testFilesStorageDsnTakesPrecedenceOverFilesDir(): void
    {
        $_ENV['FILES_STORAGE_DSN'] = $_SERVER['FILES_STORAGE_DSN'] = 'local:///srv/elsewhere';

        try {
            self::ensureKernelShutdown();
            self::bootKernel();
            $key = Uuid::v7();

            self::assertSame('/srv/elsewhere/'.$key->toRfc4122(), $this->service(FileStorage::class)->pathFor($key));
        } finally {
            unset($_ENV['FILES_STORAGE_DSN'], $_SERVER['FILES_STORAGE_DSN']);
        }
    }
}
