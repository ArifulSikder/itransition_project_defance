<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startedOn;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endedOn = null;

    #[ORM\Column(type: 'text')]
    private string $description = '';

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'project_tag')]
    private Collection $tags;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->startedOn = new \DateTimeImmutable('first day of this month');
    }

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getStartedOn(): \DateTimeImmutable
    {
        return $this->startedOn;
    }

    public function setStartedOn(\DateTimeImmutable $startedOn): self
    {
        $this->startedOn = $startedOn;

        return $this;
    }

    public function getEndedOn(): ?\DateTimeImmutable
    {
        return $this->endedOn;
    }

    public function setEndedOn(?\DateTimeImmutable $endedOn): self
    {
        $this->endedOn = $endedOn;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function clearTags(): self
    {
        $this->tags->clear();

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
