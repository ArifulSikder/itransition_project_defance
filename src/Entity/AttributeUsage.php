<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_usage_user_attribute', columns: ['user_id', 'attribute_id'])]
class AttributeUsage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AttributeDefinition $attribute;

    #[ORM\Column]
    private \DateTimeImmutable $usedAt;

    public function __construct()
    {
        $this->usedAt = new \DateTimeImmutable();
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

    public function getAttribute(): AttributeDefinition
    {
        return $this->attribute;
    }

    public function setAttribute(AttributeDefinition $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function touch(): self
    {
        $this->usedAt = new \DateTimeImmutable();

        return $this;
    }
}
