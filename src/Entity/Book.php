<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use App\Controller\BookUploadController;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'books')]
#[ApiResource(
    shortName: 'Book',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['book:list']],
        ),
        new Get(
            normalizationContext: ['groups' => ['book:read']],
        ),
        new Post(
            uriTemplate: '/books/upload',
            controller: BookUploadController::class,
            normalizationContext: ['groups' => ['book:read']],
            deserialize: false,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(
                summary: 'Upload a PDF or EPUB file',
                description: 'Upload a PDF or EPUB book file. The file will be parsed and split into pages, then persisted to the database.',
                requestBody: new \ApiPlatform\OpenApi\Model\RequestBody(
                    description: 'Book file upload',
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                        'description' => 'The PDF or EPUB file to upload',
                                    ],
                                ],
                                'required' => ['file'],
                            ],
                        ],
                    ]),
                ),
            ),
        ),
        new Delete(),
    ],
)]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['book:list', 'book:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 500)]
    #[Assert\NotBlank]
    #[Groups(['book:list', 'book:read'])]
    private string $title = '';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['book:list', 'book:read'])]
    private ?string $author = null;

    #[ORM\Column(type: 'string', length: 10)]
    #[Assert\Choice(choices: ['pdf', 'epub'])]
    #[Groups(['book:list', 'book:read'])]
    private string $format = 'pdf';

    #[ORM\Column(type: 'integer')]
    #[Groups(['book:list', 'book:read'])]
    private int $totalPages = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['book:list', 'book:read'])]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\OneToMany(targetEntity: Page::class, mappedBy: 'book', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['pageNumber' => 'ASC'])]
    #[Groups(['book:read'])]
    private Collection $pages;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
        $this->pages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): self
    {
        $this->author = $author;

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

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function setTotalPages(int $totalPages): self
    {
        $this->totalPages = $totalPages;

        return $this;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeImmutable $uploadedAt): self
    {
        $this->uploadedAt = $uploadedAt;

        return $this;
    }

    public function getPages(): Collection
    {
        return $this->pages;
    }

    public function addPage(Page $page): self
    {
        if (!$this->pages->contains($page)) {
            $this->pages->add($page);
            $page->setBook($this);
        }

        return $this;
    }

    public function removePage(Page $page): self
    {
        if ($this->pages->removeElement($page)) {
            if ($page->getBook() === $this) {
                $page->setBook(null);
            }
        }

        return $this;
    }
}
