<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Controller\FontFileController;
use App\Controller\FontUploadController;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'fonts')]
#[ApiResource(
    shortName: 'Font',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['font:list']],
            security: 'is_granted("ROLE_USER")',
        ),
        new Post(
            uriTemplate: '/fonts/upload',
            controller: FontUploadController::class,
            normalizationContext: ['groups' => ['font:read']],
            security: 'is_granted("ROLE_USER")',
            deserialize: false,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(
                summary: 'Upload a font file',
                description: 'Upload a TTF, OTF, WOFF, or WOFF2 font file.',
                requestBody: new \ApiPlatform\OpenApi\Model\RequestBody(
                    description: 'Font file upload',
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                        'description' => 'The font file to upload (TTF, OTF, WOFF, WOFF2)',
                                    ],
                                ],
                                'required' => ['file'],
                            ],
                        ],
                    ]),
                ),
            ),
        ),
        new Delete(
            security: 'is_granted("ROLE_USER") and object.getUser() == user',
        ),
        new Get(
            uriTemplate: '/fonts/{id}/file',
            controller: FontFileController::class,
            security: 'is_granted("ROLE_USER")',
            read: false,
            serialize: false,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(
                summary: 'Download the font file',
            ),
        ),
    ],
)]
class Font
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['font:list', 'font:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['font:list', 'font:read'])]
    private string $originalFilename = '';

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['font:list', 'font:read'])]
    private string $displayName = '';

    #[ORM\Column(type: 'string', length: 10)]
    #[Groups(['font:list', 'font:read'])]
    private string $format = '';

    #[ORM\Column(type: 'string', length: 512)]
    private string $filePath = '';

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['font:list', 'font:read'])]
    private \DateTimeImmutable $uploadedAt;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;
        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;
        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }
}
