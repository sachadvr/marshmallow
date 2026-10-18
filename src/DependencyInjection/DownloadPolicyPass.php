<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use App\Http\Download\DownloadPolicy;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;

final class DownloadPolicyPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $policies = $this->collectPolicies($container);
        $fallbacks = array_filter($policies, static fn (array $policy): bool => $policy['fallback']);

        if (1 !== \count($fallbacks)) {
            throw new LogicException(\sprintf('Exactly one download policy must be the fallback (#[AsDownloadPolicy(fallback: true)]), found %d: "%s".', \count($fallbacks), implode('", "', array_keys($fallbacks))));
        }

        $fallbackId = array_key_first($fallbacks);
        $fallbackPriority = $fallbacks[$fallbackId]['priority'];

        foreach ($policies as $id => $policy) {
            if ($id !== $fallbackId && $policy['priority'] <= $fallbackPriority) {
                throw new LogicException(\sprintf('The fallback download policy "%s" (priority %d) must have the lowest priority, but "%s" has priority %d.', $fallbackId, $fallbackPriority, $id, $policy['priority']));
            }
        }
    }

    /**
     * @return array<string, array{priority: int, fallback: bool}>
     */
    private function collectPolicies(ContainerBuilder $container): array
    {
        $policies = [];

        /** @var array<string, list<array<string, mixed>>> $tagged */
        $tagged = $container->findTaggedServiceIds(DownloadPolicy::TAG);

        foreach ($tagged as $id => $tags) {
            ServiceClass::assertImplements($container, $id, DownloadPolicy::class, DownloadPolicy::TAG);

            foreach ($tags as $tag) {
                $policies[$id] = [
                    'priority' => $this->priorityOf($tag, $id),
                    'fallback' => true === ($tag['fallback'] ?? false),
                ];
            }
        }

        return $policies;
    }

    /**
     * @param array<string, mixed> $tag
     */
    private function priorityOf(array $tag, string $id): int
    {
        $priority = $tag['priority'] ?? 0;

        if (!\is_int($priority)) {
            throw new InvalidArgumentException(\sprintf('The "priority" of the "%s" tag on service "%s" must be an integer.', DownloadPolicy::TAG, $id));
        }

        return $priority;
    }
}
