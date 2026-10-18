<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use App\Api\Dto\ReplaceTextInput;
use App\Api\Processor\ReplaceTextProcessor;
use App\Api\Provider\TextProvider;
use App\Realtime\Topics;
use App\Repository\TextRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TextRepository::class)]
#[ORM\Table(name: 'text')]
#[ApiResource(
    operations: [
        new Get(uriTemplate: '/text', provider: TextProvider::class),
        new Put(
            uriTemplate: '/text',
            input: ReplaceTextInput::class,
            provider: TextProvider::class,
            processor: ReplaceTextProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['text:read']],
    mercure: ['topics' => [Topics::TEXT], 'type' => 'text'],
)]
class Text
{
    public const int SINGLETON_ID = 1;
    public const int MAX_BYTES = 524288;

    #[ORM\Id]
    #[ORM\Column(type: Types::SMALLINT)]
    private int $id = self::SINGLETON_ID;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups('text:read')]
    #[ApiProperty(writable: false, required: true)]
    private string $content = '';

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups('text:read')]
    #[ApiProperty(required: true)]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups('text:read')]
    #[ApiProperty(writable: false, required: true)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(\DateTimeImmutable $now)
    {
        $this->updatedAt = $now;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
