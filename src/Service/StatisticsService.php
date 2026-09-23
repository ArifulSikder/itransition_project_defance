<?php

namespace App\Service;

use App\Entity\CurriculumVitae;
use App\Entity\Position;
use App\Repository\CurriculumVitaeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class StatisticsService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly CurriculumVitaeRepository $cvs,
    ) {
    }

    /** @return array<string, int> */
    public function snapshot(): array
    {
        return [
            'cvs_24h' => $this->cvs->countCreatedSince(new \DateTimeImmutable('-24 hours')),
            'positions' => (int) $this->em->getRepository(Position::class)->count([]),
            'candidates' => $this->users->countByRole('ROLE_CANDIDATE'),
            'recruiters' => $this->users->countByRole('ROLE_RECRUITER'),
            'cvs_total' => (int) $this->em->getRepository(CurriculumVitae::class)->count([]),
        ];
    }
}
