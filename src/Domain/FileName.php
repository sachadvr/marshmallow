<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class FileName
{
    public const int MAX_BYTES = 200;
    private const int MAX_EXTENSION_BYTES = 32;
    private const string FALLBACK = 'file';
    private const string FORBIDDEN_CHARS = '/[\x{0000}-\x{001F}\x{007F}<>:"|?*\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    private function __construct(private string $value)
    {
    }

    public static function sanitize(string $raw): self
    {
        $normalized = \Normalizer::normalize(self::toValidUtf8($raw), \Normalizer::FORM_C);
        $segments = preg_split('#[/\\\\]#', \is_string($normalized) ? $normalized : '') ?: [];
        $baseName = (string) end($segments);
        $cleaned = self::trim(self::strip('/^[\s\p{Z}.]+/u', self::strip(self::FORBIDDEN_CHARS, $baseName)));
        [$stem, $extension] = self::splitExtension($cleaned);
        $truncated = self::trim(self::truncateUtf8($stem, self::MAX_BYTES - \strlen($extension)).$extension);

        return new self('' === $truncated ? self::FALLBACK : $truncated);
    }

    public function value(): string
    {
        return $this->value;
    }

    private static function toValidUtf8(string $raw): string
    {
        return mb_check_encoding($raw, 'UTF-8') ? $raw : mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
    }

    private static function strip(string $pattern, string $value): string
    {
        return preg_replace($pattern, '', $value) ?? '';
    }

    private static function trim(string $value): string
    {
        return self::strip('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', $value);
    }

    /**
     * @return array{string, string}
     */
    private static function splitExtension(string $name): array
    {
        $dot = strrpos($name, '.');

        if (false === $dot || 0 === $dot || \strlen($name) - $dot > self::MAX_EXTENSION_BYTES) {
            return [$name, ''];
        }

        return [substr($name, 0, $dot), substr($name, $dot)];
    }

    private static function truncateUtf8(string $value, int $maxBytes): string
    {
        $result = '';

        foreach (mb_str_split($value) as $char) {
            if (\strlen($result) + \strlen($char) > $maxBytes) {
                break;
            }
            $result .= $char;
        }

        return $result;
    }
}
