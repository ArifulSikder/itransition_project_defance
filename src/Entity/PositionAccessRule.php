<?php

namespace App\Entity;

use App\Enum\FilterOperator;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PositionAccessRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'accessRules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AttributeDefinition $attribute;

    #[ORM\Column(enumType: FilterOperator::class)]
    private FilterOperator $operator = FilterOperator::Equals;

    #[ORM\Column(length: 255)]
    private string $compareValue = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function setPosition(Position $position): self
    {
        $this->position = $position;

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

    public function getOperator(): FilterOperator
    {
        return $this->operator;
    }

    public function setOperator(FilterOperator $operator): self
    {
        $this->operator = $operator;

        return $this;
    }

    public function getCompareValue(): string
    {
        return $this->compareValue;
    }

    public function setCompareValue(string $compareValue): self
    {
        $this->compareValue = $compareValue;

        return $this;
    }
}
