<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_badges')]
#[ORM\UniqueConstraint(name: 'uniq_user_badge', columns: ['user_id', 'badge_id'])]
class UserBadge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 100)]
    private string $badgeId = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $earnedAt;

    public function __construct()
    {
        $this->earnedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getBadgeId(): string { return $this->badgeId; }
    public function setBadgeId(string $badgeId): self { $this->badgeId = $badgeId; return $this; }

    public function getEarnedAt(): \DateTimeImmutable { return $this->earnedAt; }
    public function setEarnedAt(\DateTimeImmutable $earnedAt): self { $this->earnedAt = $earnedAt; return $this; }
}
