<?php

declare(strict_types=1);

namespace App\Domain;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;

abstract class DomainError extends \DomainException implements ProblemExceptionInterface
{
    public const string TYPE_PREFIX = 'urn:marshmallow:error:';

    abstract public function errorCode(): ErrorCode;

    public function getType(): string
    {
        return self::TYPE_PREFIX.$this->errorCode()->value;
    }

    public function getTitle(): string
    {
        return $this->errorCode()->value;
    }

    public function getStatus(): int
    {
        return $this->errorCode()->httpStatus();
    }

    public function getDetail(): ?string
    {
        return null;
    }

    public function getInstance(): ?string
    {
        return null;
    }
}
