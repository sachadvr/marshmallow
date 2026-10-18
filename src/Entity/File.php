<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Api\Processor\UploadFileProcessor;
use App\Api\Provider\LatestFileProvider;
use App\Realtime\Topics;
use App\Repository\FileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\Table(name: 'file')]
#[ORM\Index(name: 'idx_file_uploaded_at', columns: ['uploaded_at'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/files/latest',
            paginationEnabled: false,
            provider: LatestFileProvider::class,
        ),
        new Get(uriTemplate: '/files/{id}', requirements: ['id' => Requirement::UUID]),
        new Post(
            uriTemplate: '/files',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new OpenApiOperation(
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => new MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['file'],
                                'properties' => ['file' => ['type' => 'string', 'format' => 'binary']],
                            ]),
                        ),
                    ]),
                    required: true,
                ),
            ),
            validate: false,
            deserialize: false,
            processor: UploadFileProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['file:read']],
    mercure: ['topics' => [Topics::FILES], 'type' => 'file'],
)]
#[ApiProperty(property: 'inlineImage', readable: true, writable: false, required: true, schema: ['readOnly' => true, 'type' => 'boolean'])]
#[ApiProperty(property: 'url', readable: true, writable: false, required: true, schema: ['readOnly' => true, 'type' => 'string'])]
class File
{
    public const int MAX_BYTES = 104857600;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        #[Groups('file:read')]
        #[ApiProperty(required: true, initializable: false)]
        private Uuid $id,
        #[ORM\Column(length: 255)]
        #[Groups('file:read')]
        #[ApiProperty(required: true, initializable: false)]
        #[SerializedName('name')]
        private string $originalName,
        #[ORM\Column(length: 127)]
        #[Groups('file:read')]
        #[ApiProperty(required: true, initializable: false)]
        private string $mimeType,
        #[ORM\Column(type: Types::INTEGER)]
        #[Groups('file:read')]
        #[ApiProperty(required: true, initializable: false)]
        private int $size,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        #[Groups('file:read')]
        #[ApiProperty(required: true, initializable: false)]
        private \DateTimeImmutable $uploadedAt,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }
}
