<?php

declare(strict_types=1);

namespace App\Http;

use App\Entity\File;
use App\Http\Download\DownloadPolicy;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\HeaderUtils;

final readonly class DownloadHeaders
{
    /**
     * @param iterable<DownloadPolicy> $policies
     */
    public function __construct(
        #[AutowireIterator(DownloadPolicy::TAG)] private iterable $policies,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function for(File $file): array
    {
        $disposition = $this->policyFor($file)->disposition($file);
        $name = $file->getOriginalName();

        return [
            'Content-Type' => $disposition->contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $disposition->inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $name,
                self::asciiFallback($name),
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=86400',
        ];
    }

    private function policyFor(File $file): DownloadPolicy
    {
        foreach ($this->policies as $policy) {
            if ($policy->supports($file)) {
                return $policy;
            }
        }

        throw new \LogicException(\sprintf('No download policy supports "%s".', $file->getMimeType()));
    }

    private static function asciiFallback(string $name): string
    {
        return preg_replace('/[^\x20-\x7e]|%/u', '_', $name) ?? 'file';
    }
}
