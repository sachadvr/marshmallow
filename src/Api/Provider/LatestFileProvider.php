<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\File;
use App\Repository\FileRepository;

/**
 * @implements ProviderInterface<File>
 */
final readonly class LatestFileProvider implements ProviderInterface
{
    public function __construct(private FileRepository $files)
    {
    }

    /**
     * @return list<File>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $latest = $this->files->findLatest();

        return null === $latest ? [] : [$latest];
    }
}
