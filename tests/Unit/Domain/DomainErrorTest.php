<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\DomainError;
use App\Domain\Exception\EmptyFile;
use App\Domain\Exception\FileNotFound;
use App\Domain\Exception\FileTooLarge;
use App\Domain\Exception\NoFileProvided;
use App\Domain\Exception\TextVersionConflict;
use App\Domain\Exception\UploadIncomplete;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DomainErrorTest extends TestCase
{
    /**
     * @return iterable<string, array{DomainError, string, int}>
     */
    public static function errors(): iterable
    {
        yield 'no file' => [new NoFileProvided(), 'NO_FILE', 400];

        yield 'empty file' => [new EmptyFile(), 'EMPTY_FILE', 400];

        yield 'upload incomplete' => [new UploadIncomplete(), 'UPLOAD_INCOMPLETE', 400];

        yield 'file too large' => [new FileTooLarge(), 'FILE_TOO_LARGE', 413];

        yield 'text conflict' => [new TextVersionConflict(), 'TEXT_CONFLICT', 409];

        yield 'not found' => [new FileNotFound(), 'NOT_FOUND', 404];
    }

    #[DataProvider('errors')]
    public function testExposesItsCodeAsAProblem(DomainError $error, string $code, int $status): void
    {
        self::assertSame('urn:marshmallow:error:'.$code, $error->getType());
        self::assertSame($code, $error->getTitle());
        self::assertSame($status, $error->getStatus());
        self::assertNull($error->getDetail());
        self::assertNull($error->getInstance());
    }
}
