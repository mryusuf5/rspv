<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'store_font_items')]
class StoreFontItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $displayName = '';

    #[ORM\Column(type: 'string', length: 50)]
    private string $category = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $originalFilename = '';

    #[ORM\Column(type: 'string', length: 10)]
    private string $format = '';

    #[ORM\Column(type: 'string', length: 512)]
    private string $filePath = '';

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $uploadedAt;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getDisplayName(): string { return $this->displayName; }
    public function setDisplayName(string $v): self { $this->displayName = $v; return $this; }

    public function getCategory(): string { return $this->category; }
    public function setCategory(string $v): self { $this->category = $v; return $this; }

    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function setOriginalFilename(string $v): self { $this->originalFilename = $v; return $this; }

    public function getFormat(): string { return $this->format; }
    public function setFormat(string $v): self { $this->format = $v; return $this; }

    public function getFilePath(): string { return $this->filePath; }
    public function setFilePath(string $v): self { $this->filePath = $v; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): self { $this->isActive = $v; return $this; }

    public function getUploadedAt(): \DateTimeImmutable { return $this->uploadedAt; }
}
