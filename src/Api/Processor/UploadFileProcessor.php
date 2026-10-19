<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\UploadFile;
use App\Entity\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProcessorInterface<mixed, File>
 */
final readonly class UploadFileProcessor implements ProcessorInterface
{
    public function __construct(private UploadFile $uploadFile)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): File
    {
        $request = $context['request'] ?? null;

        if (!$request instanceof Request) {
            throw new \LogicException('The upload operation is only reachable over HTTP.');
        }
        $file = $request->files->get('file');

        return ($this->uploadFile)($file instanceof UploadedFile ? $file : null);
    }
}
