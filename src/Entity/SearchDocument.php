<?php

namespace App\Entity;

use App\Enum\SearchType;
use App\Repository\SearchDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SearchDocumentRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_search_type_entity', columns: ['type', 'entity_id'])]
class SearchDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: SearchType::class)]
    private SearchType $type;

    #[ORM\Column]
    private int $entityId;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    private string $body = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $route = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): SearchType
    {
        return $this->type;
    }

    public function setType(SearchType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): self
    {
        $this->route = $route;

        return $this;
    }
}
