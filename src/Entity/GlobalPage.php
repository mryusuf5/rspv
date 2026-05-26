<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'global_pages')]
#[ORM\Index(columns: ['global_book_id', 'page_number'], name: 'idx_global_page_book_number')]
class GlobalPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GlobalBook::class, inversedBy: 'pages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?GlobalBook $globalBook = null;

    #[ORM\Column(type: 'integer')]
    private int $pageNumber = 1;

    #[ORM\Column(type: 'text')]
    private string $content = '';

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $chapterTitle = null;

    #[ORM\Column(type: 'integer')]
    private int $wordCount = 0;

    public function getId(): ?int { return $this->id; }

    public function getGlobalBook(): ?GlobalBook { return $this->globalBook; }
    public function setGlobalBook(?GlobalBook $globalBook): self { $this->globalBook = $globalBook; return $this; }

    public function getPageNumber(): int { return $this->pageNumber; }
    public function setPageNumber(int $pageNumber): self { $this->pageNumber = $pageNumber; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self
    {
        $this->content = $content;
        $this->wordCount = str_word_count($content);
        return $this;
    }

    public function getChapterTitle(): ?string { return $this->chapterTitle; }
    public function setChapterTitle(?string $chapterTitle): self { $this->chapterTitle = $chapterTitle; return $this; }

    public function getWordCount(): int { return $this->wordCount; }
}
