<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_purchases')]
#[ORM\UniqueConstraint(name: 'uniq_user_item', columns: ['user_id', 'item_type', 'item_id'])]
class UserPurchase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 20)]
    private string $itemType = '';

    #[ORM\Column(type: 'string', length: 100)]
    private string $itemId = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $purchasedAt;

    public function __construct()
    {
        $this->purchasedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $v): self { $this->user = $v; return $this; }

    public function getItemType(): string { return $this->itemType; }
    public function setItemType(string $v): self { $this->itemType = $v; return $this; }

    public function getItemId(): string { return $this->itemId; }
    public function setItemId(string $v): self { $this->itemId = $v; return $this; }

    public function getPurchasedAt(): \DateTimeImmutable { return $this->purchasedAt; }
}
