<?php

declare(strict_types=1);

namespace App\Storage;

final readonly class Dsn
{
    private const string PATTERN = '~^(?<scheme>[a-z][a-z0-9+.-]*)://(?<host>[^/?#]*)(?<path>[^?#]*)(?:\?(?<query>[^#]*))?$~i';

    /**
     * @param array<string, string> $options
     */
    public function __construct(
        public string $scheme,
        public string $host = '',
        public string $path = '',
        public array $options = [],
    ) {
    }

    public static function fromString(string $dsn): self
    {
        if (1 !== preg_match(self::PATTERN, $dsn, $parts)) {
            throw new \InvalidArgumentException('Invalid file storage DSN, expected "<scheme>://[host][/path][?options]".');
        }

        return new self(strtolower($parts['scheme']), $parts['host'], $parts['path'], self::options($parts['query'] ?? ''));
    }

    /**
     * @return array<string, string>
     */
    private static function options(string $query): array
    {
        $options = [];

        foreach (explode('&', $query) as $pair) {
            if ('' === $pair) {
                continue;
            }
            [$name, $value] = explode('=', $pair, 2) + [1 => ''];
            $options[urldecode($name)] = urldecode($value);
        }

        return $options;
    }
}
