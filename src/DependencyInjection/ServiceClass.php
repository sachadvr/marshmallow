<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class ServiceClass
{
    /**
     * @param class-string $interface
     */
    public static function assertImplements(ContainerBuilder $container, string $id, string $interface, string $tag): void
    {
        $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass());

        if (!\is_string($class) || !is_subclass_of($class, $interface)) {
            throw new InvalidArgumentException(\sprintf('Service "%s" is tagged "%s" but does not implement "%s".', $id, $tag, $interface));
        }
    }
}
