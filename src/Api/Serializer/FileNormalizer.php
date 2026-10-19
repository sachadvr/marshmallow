<?php

declare(strict_types=1);

namespace App\Api\Serializer;

use App\Entity\File;
use App\Http\Controller\DownloadFileController;
use App\Http\Download\RasterImagePolicy;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class FileNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string GROUP = 'file:read';
    private const string ALREADY_CALLED = 'app_file_normalizer_already_called';
    private const array VIRTUAL_PROPERTIES = ['inlineImage', 'url'];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RasterImagePolicy $rasterImages,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        \assert($data instanceof File);

        $context[self::ALREADY_CALLED] = true;
        $ignored = $context[AbstractNormalizer::IGNORED_ATTRIBUTES] ?? [];
        $context[AbstractNormalizer::IGNORED_ATTRIBUTES] = [...(\is_array($ignored) ? $ignored : []), ...self::VIRTUAL_PROPERTIES];

        $normalized = $this->normalizer->normalize($data, $format, $context);
        \assert(\is_array($normalized));

        $normalized['inlineImage'] = $this->rasterImages->supports($data);
        $normalized['url'] = $this->urlGenerator->generate(DownloadFileController::ROUTE, ['id' => $data->getId()->toRfc4122()]);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof File
            && !isset($context[self::ALREADY_CALLED])
            && \in_array(self::GROUP, (array) ($context[AbstractNormalizer::GROUPS] ?? []), true);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [File::class => false];
    }
}
