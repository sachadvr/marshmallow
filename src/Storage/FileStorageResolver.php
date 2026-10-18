<?php

declare(strict_types=1);

namespace App\Storage;

use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;

final readonly class FileStorageResolver
{
    /**
     * @param ServiceProviderInterface<FileStorageFactory> $factories
     */
    public function __construct(
        #[AutowireLocator(FileStorageFactory::TAG, indexAttribute: 'scheme')] private ServiceProviderInterface $factories,
    ) {
    }

    public function fromDsn(string $dsn): FileStorage
    {
        $parsed = Dsn::fromString($dsn);

        if (!$this->factories->has($parsed->scheme)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported file storage scheme "%s". Available schemes: "%s".', $parsed->scheme, implode('", "', array_keys($this->factories->getProvidedServices()))));
        }

        return $this->factories->get($parsed->scheme)->create($parsed);
    }
}
