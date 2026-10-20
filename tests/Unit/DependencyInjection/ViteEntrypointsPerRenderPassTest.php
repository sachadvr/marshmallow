<?php

declare(strict_types=1);

namespace App\Tests\Unit\DependencyInjection;

use App\DependencyInjection\ViteEntrypointsPerRenderPass;
use Pentatrion\ViteBundle\Service\EntrypointsLookup;
use Pentatrion\ViteBundle\Service\FileAccessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

final class ViteEntrypointsPerRenderPassTest extends TestCase
{
    public function testMakesTheServicesReadingEntrypointsNonShared(): void
    {
        $container = new ContainerBuilder();
        $container->register('vite.accessor', FileAccessor::class);
        $container->register('vite.lookup[_default]', EntrypointsLookup::class);
        $container->register('unrelated', \stdClass::class);

        new ViteEntrypointsPerRenderPass()->process($container);

        self::assertFalse($container->getDefinition('vite.accessor')->isShared());
        self::assertFalse($container->getDefinition('vite.lookup[_default]')->isShared());
        self::assertTrue($container->getDefinition('unrelated')->isShared());
    }

    public function testFailsWhenTheBundleNoLongerDefinesThem(): void
    {
        $container = new ContainerBuilder();
        $container->register('vite.accessor', FileAccessor::class);

        $this->expectException(LogicException::class);

        new ViteEntrypointsPerRenderPass()->process($container);
    }
}
