<?php

declare(strict_types=1);

namespace App\Tests\Unit\DependencyInjection;

use App\DependencyInjection\DownloadPolicyPass;
use App\Http\Download\ActiveContentPolicy;
use App\Http\Download\DownloadPolicy;
use App\Http\Download\FallbackPolicy;
use App\Http\Download\RasterImagePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;

final class DownloadPolicyPassTest extends TestCase
{
    public function testAcceptsASingleFallbackWithTheLowestPriority(): void
    {
        $container = new ContainerBuilder();
        $this->policy($container, 'active', ActiveContentPolicy::class, 100);
        $this->policy($container, 'raster', RasterImagePolicy::class, 0);
        $this->policy($container, 'fallback', FallbackPolicy::class, -100, fallback: true);

        new DownloadPolicyPass()->process($container);

        $this->addToAssertionCount(1);
    }

    public function testRejectsAMissingFallback(): void
    {
        $container = new ContainerBuilder();
        $this->policy($container, 'raster', RasterImagePolicy::class, 0);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Exactly one download policy must be the fallback (#[AsDownloadPolicy(fallback: true)]), found 0: "".');

        new DownloadPolicyPass()->process($container);
    }

    public function testRejectsTwoFallbacks(): void
    {
        $container = new ContainerBuilder();
        $this->policy($container, 'fallback', FallbackPolicy::class, -100, fallback: true);
        $this->policy($container, 'other', RasterImagePolicy::class, -200, fallback: true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('found 2: "fallback", "other".');

        new DownloadPolicyPass()->process($container);
    }

    public function testRejectsAFallbackThatIsNotRankedLast(): void
    {
        $container = new ContainerBuilder();
        $this->policy($container, 'fallback', FallbackPolicy::class, 0, fallback: true);
        $this->policy($container, 'raster', RasterImagePolicy::class, 0);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The fallback download policy "fallback" (priority 0) must have the lowest priority, but "raster" has priority 0.');

        new DownloadPolicyPass()->process($container);
    }

    public function testRejectsATaggedServiceThatIsNotAPolicy(): void
    {
        $container = new ContainerBuilder();
        $this->policy($container, 'fallback', FallbackPolicy::class, -100, fallback: true);
        $this->policy($container, 'impostor', \stdClass::class, 0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Service "impostor" is tagged "%s" but does not implement "%s".', DownloadPolicy::TAG, DownloadPolicy::class));

        new DownloadPolicyPass()->process($container);
    }

    /**
     * @param class-string $class
     */
    private function policy(ContainerBuilder $container, string $id, string $class, int $priority, bool $fallback = false): void
    {
        $container->register($id, $class)->addTag(DownloadPolicy::TAG, ['priority' => $priority, 'fallback' => $fallback]);
    }
}
