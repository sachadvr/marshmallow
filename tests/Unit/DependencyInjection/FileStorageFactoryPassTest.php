<?php

declare(strict_types=1);

namespace App\Tests\Unit\DependencyInjection;

use App\DependencyInjection\FileStorageFactoryPass;
use App\Storage\FileStorageFactory;
use App\Storage\LocalFileStorageFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;

final class FileStorageFactoryPassTest extends TestCase
{
    public function testAcceptsOneFactoryPerScheme(): void
    {
        $container = new ContainerBuilder();
        $this->factory($container, 'local', LocalFileStorageFactory::class, ['scheme' => 'local']);
        $this->factory($container, 'memory', LocalFileStorageFactory::class, ['scheme' => 'memory']);

        new FileStorageFactoryPass()->process($container);

        $this->addToAssertionCount(1);
    }

    public function testRejectsTwoFactoriesForTheSameScheme(): void
    {
        $container = new ContainerBuilder();
        $this->factory($container, 'local', LocalFileStorageFactory::class, ['scheme' => 'local']);
        $this->factory($container, 'local_bis', LocalFileStorageFactory::class, ['scheme' => 'local']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The file storage scheme "local" is declared by both "local" and "local_bis".');

        new FileStorageFactoryPass()->process($container);
    }

    public function testRejectsATagWithoutScheme(): void
    {
        $container = new ContainerBuilder();
        $this->factory($container, 'local', LocalFileStorageFactory::class, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a "scheme"');

        new FileStorageFactoryPass()->process($container);
    }

    public function testRejectsATaggedServiceThatIsNotAFactory(): void
    {
        $container = new ContainerBuilder();
        $this->factory($container, 'impostor', \stdClass::class, ['scheme' => 'local']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Service "impostor" is tagged "%s" but does not implement "%s".', FileStorageFactory::TAG, FileStorageFactory::class));

        new FileStorageFactoryPass()->process($container);
    }

    /**
     * @param class-string          $class
     * @param array<string, string> $attributes
     */
    private function factory(ContainerBuilder $container, string $id, string $class, array $attributes): void
    {
        $container->register($id, $class)->addTag(FileStorageFactory::TAG, $attributes);
    }
}
