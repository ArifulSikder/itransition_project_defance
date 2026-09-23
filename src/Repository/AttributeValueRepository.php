<?php

namespace App\Repository;

use App\Entity\AttributeValue;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\AttributeType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttributeValue>
 */
class AttributeValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributeValue::class);
    }

    /** @return list<AttributeValue> */
    public function findForOwner(User $owner): array
    {
        return $this->createQueryBuilder('v')
            ->select('v', 'a')
            ->innerJoin('v.attribute', 'a')
            ->addSelect('a')
            ->andWhere('v.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('a.category', 'ASC')
            ->addOrderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Loads every value for many users in one query so list pages never loop.
     *
     * @param list<int> $userIds
     * @return array<int, list<AttributeValue>>
     */
    public function mapByOwnerIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $values = $this->createQueryBuilder('v')
            ->select('v', 'a', 'o')
            ->innerJoin('v.attribute', 'a')
            ->innerJoin('v.owner', 'o')
            ->andWhere('o.id IN (:ids)')
            ->setParameter('ids', $userIds)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($values as $value) {
            $map[$value->getOwner()->getId()][] = $value;
        }

        return $map;
    }

    /**
     * Aggregates published, still-accessible CVs for a position from the shared
     * candidate values — not from a copied CV document.
     *
     * @return array<int, array{avg?: ?float, min?: ?float, max?: ?float, count: int, yes?: int, no?: int}>
     */
    public function aggregatesForPosition(Position $position): array
    {
        $numeric = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.attribute) AS attributeId, AVG(v.rawValue) AS avgValue, MIN(v.rawValue) AS minValue, MAX(v.rawValue) AS maxValue, COUNT(v.id) AS filled')
            ->innerJoin('v.owner', 'o')
            ->innerJoin('o.cvs', 'c')
            ->innerJoin('c.position', 'p')
            ->innerJoin('p.templateAttributes', 'ta', 'WITH', 'ta.attribute = v.attribute')
            ->innerJoin('v.attribute', 'a')
            ->andWhere('c.position = :position')
            ->andWhere('c.published = true')
            ->andWhere('a.type = :numeric')
            ->andWhere('v.rawValue IS NOT NULL')
            ->andWhere('v.rawValue <> :empty')
            ->setParameter('position', $position)
            ->setParameter('numeric', AttributeType::Numeric)
            ->setParameter('empty', '')
            ->groupBy('v.attribute')
            ->getQuery()
            ->getArrayResult();

        $bool = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.attribute) AS attributeId, v.rawValue AS flag, COUNT(v.id) AS filled')
            ->innerJoin('v.owner', 'o')
            ->innerJoin('o.cvs', 'c')
            ->innerJoin('c.position', 'p')
            ->innerJoin('p.templateAttributes', 'ta', 'WITH', 'ta.attribute = v.attribute')
            ->innerJoin('v.attribute', 'a')
            ->andWhere('c.position = :position')
            ->andWhere('c.published = true')
            ->andWhere('a.type = :bool')
            ->andWhere('v.rawValue IS NOT NULL')
            ->andWhere('v.rawValue <> :empty')
            ->setParameter('position', $position)
            ->setParameter('bool', AttributeType::Boolean)
            ->setParameter('empty', '')
            ->groupBy('v.attribute')
            ->addGroupBy('v.rawValue')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($numeric as $row) {
            $map[(int) $row['attributeId']] = [
                'avg' => $row['avgValue'] !== null ? (float) $row['avgValue'] : null,
                'min' => $row['minValue'] !== null ? (float) $row['minValue'] : null,
                'max' => $row['maxValue'] !== null ? (float) $row['maxValue'] : null,
                'count' => (int) $row['filled'],
            ];
        }
        foreach ($bool as $row) {
            $id = (int) $row['attributeId'];
            $map[$id] ??= ['yes' => 0, 'no' => 0, 'count' => 0];
            $filled = (int) $row['filled'];
            $map[$id]['count'] += $filled;
            $flag = mb_strtolower((string) $row['flag']);
            if (in_array($flag, ['1', 'true', 'yes', 'on'], true)) {
                $map[$id]['yes'] += $filled;
            } else {
                $map[$id]['no'] += $filled;
            }
        }

        return $map;
    }
}
