<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Dto\ReplaceTextInput;
use App\Application\ReplaceText;
use App\Entity\Text;

/**
 * @implements ProcessorInterface<ReplaceTextInput, Text>
 */
final readonly class ReplaceTextProcessor implements ProcessorInterface
{
    public function __construct(private ReplaceText $replaceText)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Text
    {
        return ($this->replaceText)((string) $data->content, (int) $data->baseVersion);
    }
}
