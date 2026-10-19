<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Text;
use App\Repository\TextRepository;

/**
 * @implements ProviderInterface<Text>
 */
final readonly class TextProvider implements ProviderInterface
{
    public function __construct(private TextRepository $texts)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Text
    {
        return $this->texts->get();
    }
}
