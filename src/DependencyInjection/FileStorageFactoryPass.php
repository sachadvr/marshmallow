<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use App\Storage\FileStorageFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;

final class FileStorageFactoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $owners = [];
        /** @var array<string, list<array<string, mixed>>> $tagged */
        $tagged = $container->findTaggedServiceIds(FileStorageFactory::TAG);

        foreach ($tagged as $id => $tags) {
            ServiceClass::assertImplements($container, $id, FileStorageFactory::class, FileStorageFactory::TAG);

            foreach ($tags as $tag) {
                $scheme = $tag['scheme'] ?? null;

                if (!\is_string($scheme) || '' === $scheme) {
                    throw new InvalidArgumentException(\sprintf('The "%s" tag on service "%s" needs a "scheme" (use #[AsFileStorageFactory(scheme: ...)]).', FileStorageFactory::TAG, $id));
                }

                if (isset($owners[$scheme])) {
                    throw new LogicException(\sprintf('The file storage scheme "%s" is declared by both "%s" and "%s".', $scheme, $owners[$scheme], $id));
                }
                $owners[$scheme] = $id;
            }
        }
    }
}
