<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exception\TextVersionConflict;
use App\Entity\Text;
use App\Repository\TextRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class ReplaceText
{
    public function __construct(
        private TextRepository $texts,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(string $content, int $baseVersion): Text
    {
        $text = $this->texts->get();

        if ($baseVersion !== $text->getVersion()) {
            throw new TextVersionConflict();
        }

        if ($content === $text->getContent()) {
            return $text;
        }

        $text->setContent($content);
        $text->setUpdatedAt($this->clock->now());
        $this->entityManager->flush();

        return $text;
    }
}
