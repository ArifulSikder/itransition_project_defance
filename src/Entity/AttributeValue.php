<?php

namespace App\Entity;

use App\Repository\AttributeValueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttributeValueRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_user_attribute', columns: ['owner_id', 'attribute_id'])]
class AttributeValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attributeValues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AttributeDefinition $attribute;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rawValue = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;

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

    public function getRawValue(): ?string
    {
        return $this->rawValue;
    }

    public function setRawValue(?string $rawValue): self
    {
        $this->rawValue = $rawValue;

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->rawValue === null || trim($this->rawValue) === '';
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
