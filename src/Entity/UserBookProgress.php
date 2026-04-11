<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use App\Controller\ProgressController;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_book_progress')]
#[ORM\UniqueConstraint(name: 'uniq_user_book', columns: ['user_id', 'book_id'])]
#[UniqueEntity(fields: ['user', 'book'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/progress/{bookId}',
            controller: ProgressController::class,
            normalizationContext: ['groups' => ['progress:read']],
            security: 'is_granted("ROLE_USER")',
            read: false,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(
                summary: 'Get reading progress for a book',
                description: 'Returns the current reading position (page number and word index) for the authenticated user and the given book.',
            ),
        ),
        new Put(
            uriTemplate: '/progress/{bookId}',
            controller: ProgressController::class,
            normalizationContext: ['groups' => ['progress:read']],
            denormalizationContext: ['groups' => ['progress:write']],
            security: 'is_granted("ROLE_USER")',
            read: false,
            deserialize: false,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(
                summary: 'Save reading progress for a book',
                description: 'Saves (or updates) the current reading position for the authenticated user. Send the page number and the word index (0-based position of the current word within that page).',
                requestBody: new \ApiPlatform\OpenApi\Model\RequestBody(
                    description: 'Progress payload',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['pageNumber', 'wordIndex'],
                                'properties' => [
                                    'pageNumber' => ['type' => 'integer', 'minimum' => 1, 'example' => 3],
                                    'wordIndex'  => ['type' => 'integer', 'minimum' => 0, 'example' => 42, 'description' => '0-based index of the current word within the page'],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
        ),
    ],
)]
class UserBookProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['progress:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Book::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['progress:read'])]
    private Book $book;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    #[Groups(['progress:read', 'progress:write'])]
    private int $pageNumber = 1;

    #[ORM\Column(type: 'integer')]
    #[Assert\GreaterThanOrEqual(0)]
    #[Groups(['progress:read', 'progress:write'])]
    private int $wordIndex = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['progress:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getBook(): Book
    {
        return $this->book;
    }

    public function setBook(Book $book): self
    {
        $this->book = $book;

        return $this;
    }

    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    public function setPageNumber(int $pageNumber): self
    {
        $this->pageNumber = $pageNumber;

        return $this;
    }

    public function getWordIndex(): int
    {
        return $this->wordIndex;
    }

    public function setWordIndex(int $wordIndex): self
    {
        $this->wordIndex = $wordIndex;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
