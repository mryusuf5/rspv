<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $actor;

    /** 'follow_request' | 'new_follower' | 'follow_accepted' */
    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    /** follow.id — used to accept/deny from notification */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $referenceId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dismissedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getRecipient(): User { return $this->recipient; }
    public function setRecipient(User $recipient): self { $this->recipient = $recipient; return $this; }

    public function getActor(): User { return $this->actor; }
    public function setActor(User $actor): self { $this->actor = $actor; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getReferenceId(): ?int { return $this->referenceId; }
    public function setReferenceId(?int $referenceId): self { $this->referenceId = $referenceId; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getDismissedAt(): ?\DateTimeImmutable { return $this->dismissedAt; }
    public function setDismissedAt(?\DateTimeImmutable $dismissedAt): self { $this->dismissedAt = $dismissedAt; return $this; }
}
