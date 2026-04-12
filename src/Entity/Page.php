<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'pages')]
#[ORM\Index(columns: ['book_id', 'page_number'], name: 'idx_page_book_number')]
#[ApiResource(
    shortName: 'Page',
    operations: [
        new GetCollection(
            uriTemplate: '/books/{bookId}/pages',
            uriVariables: [
                'bookId' => new \ApiPlatform\Metadata\Link(
                    fromClass: Book::class,
                    toProperty: 'book',
                ),
            ],
            normalizationContext: ['groups' => ['page:list']],
        ),
        new Get(
            uriTemplate: '/books/{bookId}/pages/{pageNumber}',
            uriVariables: [
                'bookId' => new \ApiPlatform\Metadata\Link(
                    fromClass: Book::class,
                    toProperty: 'book',
                ),
                'pageNumber' => new \ApiPlatform\Metadata\Link(
                    fromClass: Page::class,
                    identifiers: ['pageNumber'],
                ),
            ],
            normalizationContext: ['groups' => ['page:read']],
        ),
    ],
)]
class Page
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['page:list', 'page:read', 'book:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'pages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Book $book = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    #[Groups(['page:list', 'page:read', 'book:read'])]
    private int $pageNumber = 1;

    #[ORM\Column(type: 'text')]
    #[Groups(['page:read', 'book:read'])]
    private string $content = '';

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    #[Groups(['page:list', 'page:read', 'book:read'])]
    private ?string $chapterTitle = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['page:list', 'page:read', 'book:read'])]
    private int $wordCount = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(?Book $book): self
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

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        $this->wordCount = str_word_count($content);

        return $this;
    }

    public function getChapterTitle(): ?string
    {
        return $this->chapterTitle;
    }

    public function setChapterTitle(?string $chapterTitle): self
    {
        $this->chapterTitle = $chapterTitle;

        return $this;
    }

    public function getWordCount(): int
    {
        return $this->wordCount;
    }
}
