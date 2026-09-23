<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_like_recruiter_cv', columns: ['recruiter_id', 'cv_id'])]
class CvLike
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recruiter;

    #[ORM\ManyToOne(inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CurriculumVitae $cv;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecruiter(): User
    {
        return $this->recruiter;
    }

    public function setRecruiter(User $recruiter): self
    {
        $this->recruiter = $recruiter;

        return $this;
    }

    public function getCv(): CurriculumVitae
    {
        return $this->cv;
    }

    public function setCv(CurriculumVitae $cv): self
    {
        $this->cv = $cv;

        return $this;
    }
}
