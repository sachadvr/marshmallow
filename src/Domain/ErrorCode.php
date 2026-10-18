<?php

declare(strict_types=1);

namespace App\Domain;

enum ErrorCode: string
{
    case NO_FILE = 'NO_FILE';
    case EMPTY_FILE = 'EMPTY_FILE';
    case UPLOAD_INCOMPLETE = 'UPLOAD_INCOMPLETE';
    case FILE_TOO_LARGE = 'FILE_TOO_LARGE';
    case TEXT_TOO_LARGE = 'TEXT_TOO_LARGE';
    case TEXT_CONFLICT = 'TEXT_CONFLICT';
    case NOT_FOUND = 'NOT_FOUND';

    public function httpStatus(): int
    {
        return match ($this) {
            self::NO_FILE, self::EMPTY_FILE, self::UPLOAD_INCOMPLETE => 400,
            self::FILE_TOO_LARGE, self::TEXT_TOO_LARGE => 413,
            self::NOT_FOUND => 404,
            self::TEXT_CONFLICT => 409,
        };
    }
}
