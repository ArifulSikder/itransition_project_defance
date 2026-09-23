<?php

namespace App\Repository;

use App\Entity\CurriculumVitae;
use App\Entity\Position;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CurriculumVitae>
 */
class CurriculumVitaeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CurriculumVitae::class);
    }

    public function findOneFor(User $owner, Position $position): ?CurriculumVitae
    {
        return $this->findOneBy(['owner' => $owner, 'position' => $position]);
    }

    /** @return list<CurriculumVitae> */
    public function findForOwner(User $owner): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'p', 'r', 'ra')
            ->distinct()
            ->innerJoin('c.position', 'p')
            ->leftJoin('p.accessRules', 'r')
            ->leftJoin('r.attribute', 'ra')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<CurriculumVitae> */
    public function findPublishedForPosition(Position $position): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'o', 'p', 'r', 'ra')
            ->distinct()
            ->innerJoin('c.owner', 'o')
            ->innerJoin('c.position', 'p')
            ->leftJoin('p.accessRules', 'r')
            ->leftJoin('r.attribute', 'ra')
            ->andWhere('c.position = :position')
            ->andWhere('c.published = true')
            ->setParameter('position', $position)
            ->orderBy('c.likeCount', 'DESC')
            ->addOrderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $ids
     * @return list<CurriculumVitae>
     */
    public function findPublishedByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->select('c', 'o', 'p', 'r', 'ra')
            ->distinct()
            ->innerJoin('c.owner', 'o')
            ->innerJoin('c.position', 'p')
            ->leftJoin('p.accessRules', 'r')
            ->leftJoin('r.attribute', 'ra')
            ->andWhere('c.id IN (:ids)')
            ->andWhere('c.published = true')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /** @return array{total: int, published: int, likes: int} */
    public function achievementStats(User $owner): array
    {
        $row = $this->createQueryBuilder('c')
            ->select('COUNT(c.id) AS total')
            ->addSelect('SUM(CASE WHEN c.published = true THEN 1 ELSE 0 END) AS publishedCount')
            ->addSelect('SUM(c.likeCount) AS likes')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'published' => (int) ($row['publishedCount'] ?? 0),
            'likes' => (int) ($row['likes'] ?? 0),
        ];
    }

    public function countCreatedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findWithRelations(int $id): ?CurriculumVitae
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'o', 'p', 'v', 'a')
            ->innerJoin('c.owner', 'o')
            ->innerJoin('c.position', 'p')
            ->leftJoin('o.attributeValues', 'v')
            ->leftJoin('v.attribute', 'a')
            ->andWhere('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
