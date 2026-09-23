<?php

namespace App\Repository;

use App\Entity\Position;
use App\Enum\PositionLevel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Position>
 */
class PositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Position::class);
    }

    /**
     * @param list<int>|null $onlyIds null keeps every position
     * @return list<Position>
     */
    public function findLatest(int $limit = 8, ?array $onlyIds = null): array
    {
        if ($onlyIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->select('p')
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults($limit);
        $this->restrictIds($qb, $onlyIds);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<int>|null $onlyIds
     * @return list<Position>
     */
    public function findPopular(int $limit = 5, ?array $onlyIds = null): array
    {
        if ($onlyIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->select('p')
            ->orderBy('p.cvCount', 'DESC')
            ->addOrderBy('p.updatedAt', 'DESC')
            ->setMaxResults($limit);
        $this->restrictIds($qb, $onlyIds);

        return $qb->getQuery()->getResult();
    }

    /** @return list<int> */
    public function findPublicIds(): array
    {
        return array_map('intval', $this->createQueryBuilder('p')
            ->select('p.id')
            ->andWhere('p.publicAccess = true')
            ->getQuery()
            ->getSingleColumnResult());
    }

    /** @return list<Position> */
    public function findForAccessCheck(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p', 'r', 'a')
            ->distinct()
            ->leftJoin('p.accessRules', 'r')
            ->leftJoin('r.attribute', 'a')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int>|null $onlyIds
     * @return \Doctrine\ORM\Query
     */
    public function listQuery(?string $level = null, ?string $company = null, ?string $tag = null, ?array $onlyIds = null)
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p');
        $this->restrictIds($qb, $onlyIds);

        if ($level) {
            $qb->andWhere('p.level = :level')
                ->setParameter('level', PositionLevel::from($level));
        }

        if ($company) {
            $qb->andWhere('LOWER(p.company) LIKE :company')
                ->setParameter('company', '%'.mb_strtolower($company).'%');
        }

        if ($tag) {
            $qb->innerJoin('p.projectTags', 't')
                ->andWhere('LOWER(t.name) = :tag')
                ->setParameter('tag', mb_strtolower($tag));
        }

        return $qb->getQuery();
    }

    public function findWithTemplate(int $id): ?Position
    {
        return $this->createQueryBuilder('p')
            ->select('p', 'ta', 'a', 'r', 'ra', 'pt')
            ->leftJoin('p.templateAttributes', 'ta')
            ->leftJoin('ta.attribute', 'a')
            ->leftJoin('p.accessRules', 'r')
            ->leftJoin('r.attribute', 'ra')
            ->leftJoin('p.projectTags', 'pt')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<int>|null $onlyIds
     */
    private function restrictIds(QueryBuilder $qb, ?array $onlyIds): void
    {
        if ($onlyIds === []) {
            $qb->andWhere('1 = 0');

            return;
        }

        if ($onlyIds !== null) {
            $qb->andWhere('p.id IN (:visibleIds)')
                ->setParameter('visibleIds', $onlyIds);
        }
    }
}
