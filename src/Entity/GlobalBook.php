<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'global_books')]
class GlobalBook
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 500)]
    private string $title = '';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $author = null;

    #[ORM\Column(type: 'string', length: 10)]
    private string $format = 'epub';

    #[ORM\Column(type: 'integer')]
    private int $totalPages = 0;

    #[ORM\Column(type: 'integer')]
    private int $totalWords = 0;

    #[ORM\Column(type: 'string', length: 255)]
    private string $originalFilename = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\OneToMany(targetEntity: GlobalPage::class, mappedBy: 'globalBook', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['pageNumber' => 'ASC'])]
    private Collection $pages;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
        $this->pages = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getAuthor(): ?string { return $this->author; }
    public function setAuthor(?string $author): self { $this->author = $author; return $this; }

    public function getFormat(): string { return $this->format; }
    public function setFormat(string $format): self { $this->format = $format; return $this; }

    public function getTotalPages(): int { return $this->totalPages; }
    public function setTotalPages(int $totalPages): self { $this->totalPages = $totalPages; return $this; }

    public function getTotalWords(): int { return $this->totalWords; }
    public function setTotalWords(int $totalWords): self { $this->totalWords = $totalWords; return $this; }

    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function setOriginalFilename(string $originalFilename): self { $this->originalFilename = $originalFilename; return $this; }

    public function getUploadedAt(): \DateTimeImmutable { return $this->uploadedAt; }

    public function getPages(): Collection { return $this->pages; }

    public function addPage(GlobalPage $page): self
    {
        if (!$this->pages->contains($page)) {
            $this->pages->add($page);
            $page->setGlobalBook($this);
        }
        return $this;
    }
}
