<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'follows')]
#[ORM\UniqueConstraint(name: 'uniq_follow', columns: ['follower_id', 'following_id'])]
class Follow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $follower;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $following;

    /** 'pending' | 'accepted' */
    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'pending';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getFollower(): User { return $this->follower; }
    public function setFollower(User $follower): self { $this->follower = $follower; return $this; }

    public function getFollowing(): User { return $this->following; }
    public function setFollowing(User $following): self { $this->following = $following; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
