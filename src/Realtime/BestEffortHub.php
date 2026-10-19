<?php

declare(strict_types=1);

namespace App\Realtime;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;

#[AsDecorator('mercure.hub.default')]
final readonly class BestEffortHub implements HubInterface
{
    public function __construct(
        #[Autowire(service: self::class.'.inner', lazy: true)] private HubInterface $inner,
        private LoggerInterface $logger,
    ) {
    }

    public function publish(Update $update): string
    {
        try {
            return $this->inner->publish($update);
        } catch (\Throwable $e) {
            $this->logger->warning('Mercure publication failed', [
                'topics' => $update->getTopics(),
                'exception' => $e,
            ]);

            return '';
        }
    }

    public function getPublicUrl(): string
    {
        return $this->inner->getPublicUrl();
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->inner->getFactory();
    }

    public function getProtocolVersion(): ProtocolVersion
    {
        return $this->inner->getProtocolVersion();
    }

    public function getCookieName(): string
    {
        return $this->inner->getCookieName();
    }
}
