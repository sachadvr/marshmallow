<?php

declare(strict_types=1);

namespace App\Http\Page;

use App\Entity\File;
use App\Entity\Text;
use App\Repository\FileRepository;
use App\Repository\TextRepository;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class PageProps
{
    public function __construct(
        private TextRepository $texts,
        private FileRepository $files,
        private NormalizerInterface $normalizer,
    ) {
    }

    /**
     * @return array{initialText: mixed, initialLatestFile: mixed, limits: array{maxUploadBytes: int, maxTextBytes: int}}
     */
    public function build(): array
    {
        $latestFile = $this->files->findLatest();

        return [
            'initialText' => $this->normalizer->normalize($this->texts->get(), 'json', ['groups' => ['text:read']]),
            'initialLatestFile' => null === $latestFile
                ? null
                : $this->normalizer->normalize($latestFile, 'json', ['groups' => ['file:read']]),
            'limits' => ['maxUploadBytes' => File::MAX_BYTES, 'maxTextBytes' => Text::MAX_BYTES],
        ];
    }
}
