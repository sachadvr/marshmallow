<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Pentatrion\ViteBundle\Service\EntrypointsLookup;
use Pentatrion\ViteBundle\Service\FileAccessor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

final class ViteEntrypointsPerRenderPass implements CompilerPassInterface
{
    private const array MEMOIZING_CLASSES = [EntrypointsLookup::class, FileAccessor::class];

    public function process(ContainerBuilder $container): void
    {
        $found = [];

        foreach ($container->getDefinitions() as $definition) {
            $class = $definition->getClass();

            if (\in_array($class, self::MEMOIZING_CLASSES, true)) {
                $definition->setShared(false);
                $found[$class] = true;
            }
        }

        if (\count($found) !== \count(self::MEMOIZING_CLASSES)) {
            throw new LogicException(\sprintf('Expected services of classes %s from pentatrion/vite-bundle: check this pass after upgrading it.', implode(', ', self::MEMOIZING_CLASSES)));
        }
    }
}
