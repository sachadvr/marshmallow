<?php

declare(strict_types=1);

namespace App\Tests\Unit\Storage;

use App\Storage\Dsn;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DsnTest extends TestCase
{
    public function testParsesALocalDsnWithAnEmptyHost(): void
    {
        $dsn = Dsn::fromString('local:///data/files');

        self::assertEquals(new Dsn('local', '', '/data/files'), $dsn);
    }

    public function testParsesHostPathAndOptions(): void
    {
        $dsn = Dsn::fromString('S3://my-bucket/uploads?region=eu-west-3&endpoint=https%3A%2F%2Fminio%3A9000&flag');

        self::assertEquals(
            new Dsn('s3', 'my-bucket', '/uploads', ['region' => 'eu-west-3', 'endpoint' => 'https://minio:9000', 'flag' => '']),
            $dsn,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDsns(): iterable
    {
        yield 'empty' => [''];

        yield 'bare path' => ['/data/files'];

        yield 'no authority' => ['local:/data/files'];

        yield 'scheme starting with a digit' => ['3s://bucket'];

        yield 'fragment' => ['local:///data#files'];
    }

    #[DataProvider('invalidDsns')]
    public function testRejectsInvalidDsnsWithoutEchoingThem(string $dsn): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file storage DSN, expected "<scheme>://[host][/path][?options]".');

        Dsn::fromString($dsn);
    }
}
