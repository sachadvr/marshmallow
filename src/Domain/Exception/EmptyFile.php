<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\DomainError;
use App\Domain\ErrorCode;

final class EmptyFile extends DomainError
{
    public function errorCode(): ErrorCode
    {
        return ErrorCode::EMPTY_FILE;
    }
}
