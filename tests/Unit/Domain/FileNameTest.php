<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\FileName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class FileNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sanitizedNames(): iterable
    {
        yield 'unix traversal' => ['../../etc/passwd', 'passwd'];

        yield 'windows traversal' => ['..\\..\\x', 'x'];

        yield 'absolute unix path' => ['/absolute/path/report.pdf', 'report.pdf'];

        yield 'absolute windows path' => ['C:\\Users\\me\\report.pdf', 'report.pdf'];

        yield 'control chars' => ["ev\u{0000}il\u{001F}.txt", 'evil.txt'];

        yield 'windows-reserved chars' => ['a<b>c:d"e|f?g*h.txt', 'abcdefgh.txt'];

        yield 'leading dot' => ['.htaccess', 'htaccess'];

        yield 'leading dots' => ['...hidden', 'hidden'];

        yield 'outer spaces' => ['  spaced  .txt  ', 'spaced  .txt'];

        yield 'bidi override' => ["invoice\u{202E}txt.exe", 'invoicetxt.exe'];

        yield 'bidi isolate' => ["a\u{2066}b\u{2069}.txt", 'ab.txt'];

        yield 'accents kept' => ['photo été.png', 'photo été.png'];
    }

    #[DataProvider('sanitizedNames')]
    public function testSanitizes(string $raw, string $expected): void
    {
        self::assertSame($expected, FileName::sanitize($raw)->value());
    }

    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(['...'])]
    #[TestWith(['../'])]
    #[TestWith(['<>:"|?*'])]
    #[TestWith(["\u{0000}"])]
    public function testFallsBackToFile(string $raw): void
    {
        self::assertSame('file', FileName::sanitize($raw)->value());
    }

    public function testNormalizesToNfc(): void
    {
        self::assertSame("\u{00E9}te.txt", FileName::sanitize("e\u{0301}te.txt")->value());
    }

    public function testTruncatesToTheByteBudgetAndKeepsTheExtension(): void
    {
        $result = FileName::sanitize(str_repeat('a', 300).'.tar')->value();

        self::assertSame(FileName::MAX_BYTES, \strlen($result));
        self::assertStringEndsWith('.tar', $result);
    }

    public function testNeverSplitsAMultiByteCharacter(): void
    {
        $result = FileName::sanitize(str_repeat('é', 150).'.png')->value();

        self::assertLessThanOrEqual(FileName::MAX_BYTES, \strlen($result));
        self::assertMatchesRegularExpression('/^é+\.png$/u', $result);
    }

    public function testTreatsAnOversizedExtensionAsPartOfTheName(): void
    {
        $result = FileName::sanitize('a.'.str_repeat('b', 300))->value();

        self::assertLessThanOrEqual(FileName::MAX_BYTES, \strlen($result));
        self::assertStringStartsWith('a.b', $result);
    }

    public function testReplacesInvalidUtf8(): void
    {
        $result = FileName::sanitize("bad\xFFname.txt")->value();

        self::assertTrue(mb_check_encoding($result, 'UTF-8'));
        self::assertStringEndsWith('name.txt', $result);
    }

    #[TestWith(['../x.png'])]
    #[TestWith([' .a b. '])]
    #[TestWith(['x\\y/z.txt'])]
    public function testIsIdempotent(string $raw): void
    {
        $once = FileName::sanitize($raw)->value();

        self::assertSame($once, FileName::sanitize($once)->value());
    }

    public function testIsIdempotentAfterTruncation(): void
    {
        $once = FileName::sanitize(str_repeat('é', 150).'.png')->value();

        self::assertSame($once, FileName::sanitize($once)->value());
    }
}
