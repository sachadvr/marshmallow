<?php

declare(strict_types=1);

namespace App\Tests\Unit\Storage;

use App\Storage\FileStorageResolver;
use App\Storage\LocalFileStorage;
use App\Storage\LocalFileStorageFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Uid\Uuid;

final class FileStorageResolverTest extends TestCase
{
    public function testBuildsTheStorageWithTheFactoryOfTheScheme(): void
    {
        $storage = $this->resolver()->fromDsn('local:///data/files');

        self::assertInstanceOf(LocalFileStorage::class, $storage);
        $key = Uuid::v7();
        self::assertSame('/data/files/'.$key->toRfc4122(), $storage->pathFor($key));
    }

    public function testAnUnknownSchemeListsTheAvailableOnes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported file storage scheme "s3". Available schemes: "local", "tmp".');

        $this->resolver()->fromDsn('s3://bucket/prefix');
    }

    public function testTheLocalFactoryRequiresAnAbsolutePathWithoutHost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The local file storage DSN must be "local:///absolute/path".');

        $this->resolver()->fromDsn('local://var/files');
    }

    private function resolver(): FileStorageResolver
    {
        $factory = static fn (): LocalFileStorageFactory => new LocalFileStorageFactory();

        /** @var ServiceLocator<LocalFileStorageFactory> $factories */
        $factories = new ServiceLocator(['local' => $factory, 'tmp' => $factory]);

        return new FileStorageResolver($factories);
    }
}
