<?php

namespace App\Entity;

use App\Enum\AttributeCategory;
use App\Enum\AttributeType;
use App\Repository\AttributeDefinitionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AttributeDefinitionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_attribute_name', columns: ['name'])]
#[UniqueEntity(fields: ['name'], message: 'Attribute name must be globally unique.')]
class AttributeDefinition
{
    public const BUILTIN_FIRST_NAME = 'first_name';
    public const BUILTIN_LAST_NAME = 'last_name';
    public const BUILTIN_LOCATION = 'location';
    public const BUILTIN_PHOTO = 'personal_photo';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(enumType: AttributeCategory::class)]
    private AttributeCategory $category = AttributeCategory::Personal;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(enumType: AttributeType::class)]
    private AttributeType $type = AttributeType::String;

    /** @var list<string>|null dropdown options */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $options = null;

    #[ORM\Column]
    private bool $builtin = false;

    #[ORM\Column(nullable: true)]
    private ?int $minLength = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxLength = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pattern = null;

    #[ORM\Column(nullable: true)]
    private ?float $minValue = null;

    #[ORM\Column(nullable: true)]
    private ?float $maxValue = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCategory(): AttributeCategory
    {
        return $this->category;
    }

    public function setCategory(AttributeCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getType(): AttributeType
    {
        return $this->type;
    }

    public function setType(AttributeType $type): self
    {
        $this->type = $type;

        return $this;
    }

    /** @return list<string> */
    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    /** @param list<string>|null $options */
    public function setOptions(?array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function isBuiltin(): bool
    {
        return $this->builtin;
    }

    public function setBuiltin(bool $builtin): self
    {
        $this->builtin = $builtin;

        return $this;
    }

    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    public function setMinLength(?int $minLength): self
    {
        $this->minLength = $minLength;

        return $this;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function setMaxLength(?int $maxLength): self
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function setPattern(?string $pattern): self
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function getMinValue(): ?float
    {
        return $this->minValue;
    }

    public function setMinValue(?float $minValue): self
    {
        $this->minValue = $minValue;

        return $this;
    }

    public function getMaxValue(): ?float
    {
        return $this->maxValue;
    }

    public function setMaxValue(?float $maxValue): self
    {
        $this->maxValue = $maxValue;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
