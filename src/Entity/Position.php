<?php

namespace App\Entity;

use App\Enum\PositionLevel;
use App\Repository\PositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PositionRepository::class)]
class Position
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    private string $shortDescription = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(enumType: PositionLevel::class)]
    private PositionLevel $level = PositionLevel::Middle;

    #[ORM\Column]
    private bool $publicAccess = true;

    #[ORM\Column]
    private int $maxProjects = 3;

    #[ORM\Column]
    private int $cvCount = 0;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, PositionAttribute> */
    #[ORM\OneToMany(targetEntity: PositionAttribute::class, mappedBy: 'position', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $templateAttributes;

    /** @var Collection<int, PositionAccessRule> */
    #[ORM\OneToMany(targetEntity: PositionAccessRule::class, mappedBy: 'position', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $accessRules;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'position_project_tag')]
    private Collection $projectTags;

    /** @var Collection<int, CurriculumVitae> */
    #[ORM\OneToMany(targetEntity: CurriculumVitae::class, mappedBy: 'position')]
    private Collection $cvs;

    /** @var Collection<int, DiscussionPost> */
    #[ORM\OneToMany(targetEntity: DiscussionPost::class, mappedBy: 'position', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $discussionPosts;

    public function __construct()
    {
        $this->templateAttributes = new ArrayCollection();
        $this->accessRules = new ArrayCollection();
        $this->projectTags = new ArrayCollection();
        $this->cvs = new ArrayCollection();
        $this->discussionPosts = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getLevel(): PositionLevel
    {
        return $this->level;
    }

    public function setLevel(PositionLevel $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function isPublicAccess(): bool
    {
        return $this->publicAccess;
    }

    public function setPublicAccess(bool $publicAccess): self
    {
        $this->publicAccess = $publicAccess;

        return $this;
    }

    public function getMaxProjects(): int
    {
        return $this->maxProjects;
    }

    public function setMaxProjects(int $maxProjects): self
    {
        $this->maxProjects = $maxProjects;

        return $this;
    }

    public function getCvCount(): int
    {
        return $this->cvCount;
    }

    public function setCvCount(int $cvCount): self
    {
        $this->cvCount = $cvCount;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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

    /** @return Collection<int, PositionAttribute> */
    public function getTemplateAttributes(): Collection
    {
        return $this->templateAttributes;
    }

    public function addTemplateAttribute(PositionAttribute $item): self
    {
        if (!$this->templateAttributes->contains($item)) {
            $this->templateAttributes->add($item);
            $item->setPosition($this);
        }

        return $this;
    }

    public function clearTemplateAttributes(): self
    {
        $this->templateAttributes->clear();

        return $this;
    }

    /** @return Collection<int, PositionAccessRule> */
    public function getAccessRules(): Collection
    {
        return $this->accessRules;
    }

    public function addAccessRule(PositionAccessRule $rule): self
    {
        if (!$this->accessRules->contains($rule)) {
            $this->accessRules->add($rule);
            $rule->setPosition($this);
        }

        return $this;
    }

    public function clearAccessRules(): self
    {
        $this->accessRules->clear();

        return $this;
    }

    /** @return Collection<int, Tag> */
    public function getProjectTags(): Collection
    {
        return $this->projectTags;
    }

    public function addProjectTag(Tag $tag): self
    {
        if (!$this->projectTags->contains($tag)) {
            $this->projectTags->add($tag);
        }

        return $this;
    }

    public function clearProjectTags(): self
    {
        $this->projectTags->clear();

        return $this;
    }

    /** @return Collection<int, CurriculumVitae> */
    public function getCvs(): Collection
    {
        return $this->cvs;
    }

    /** @return Collection<int, DiscussionPost> */
    public function getDiscussionPosts(): Collection
    {
        return $this->discussionPosts;
    }
}
