<?php

namespace App\Repository;

use App\Entity\AttributeDefinition;
use App\Entity\User;
use App\Enum\AttributeCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttributeDefinition>
 */
class AttributeDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributeDefinition::class);
    }

    /** @return list<AttributeDefinition> */
    public function findLibrary(?string $prefix = null, ?AttributeCategory $category = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a')
            ->orderBy('a.category', 'ASC')
            ->addOrderBy('a.name', 'ASC');

        if ($prefix) {
            $qb->andWhere('LOWER(a.name) LIKE :prefix')
                ->setParameter('prefix', mb_strtolower($prefix).'%');
        }

        if ($category) {
            $qb->andWhere('a.category = :category')
                ->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<AttributeDefinition> */
    public function findRecentlyUsed(User $user, int $limit = 8): array
    {
        return $this->createQueryBuilder('a')
            ->select('a')
            ->innerJoin('App\Entity\AttributeUsage', 'u', 'WITH', 'u.attribute = a AND u.user = :user')
            ->setParameter('user', $user)
            ->orderBy('u.usedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<AttributeDefinition> */
    public function findBuiltins(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a')
            ->andWhere('a.builtin = true')
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByCode(string $code): ?AttributeDefinition
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * @param list<int> $ids
     * @return array<int, AttributeDefinition>
     */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $map = [];
        foreach ($this->createQueryBuilder('a')
            ->select('a')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult() as $definition) {
            $map[(int) $definition->getId()] = $definition;
        }

        return $map;
    }
}
