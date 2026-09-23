<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    /** @var array<string, Tag> */
    private array $pending = [];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /** @return list<Tag> */
    public function suggest(string $prefix, int $limit = 12): array
    {
        return $this->createQueryBuilder('t')
            ->select('t')
            ->andWhere('LOWER(t.name) LIKE :prefix')
            ->setParameter('prefix', mb_strtolower($prefix).'%')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneByName(string $name): ?Tag
    {
        return $this->createQueryBuilder('t')
            ->andWhere('LOWER(t.name) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Tag cloud weights: recruiters see CV-linked tags, candidates see position tags.
     *
     * @return list<array{name: string, weight: int}>
     */
    public function cloudWeights(bool $forRecruiters): array
    {
        $conn = $this->getEntityManager()->getConnection();

        if ($forRecruiters) {
            $sql = <<<SQL
                SELECT t.name AS name, COUNT(pt.project_id) AS weight
                FROM tag t
                INNER JOIN project_tag pt ON pt.tag_id = t.id
                GROUP BY t.id, t.name
                HAVING COUNT(pt.project_id) > 0
                ORDER BY weight DESC, t.name ASC
            SQL;
        } else {
            $sql = <<<SQL
                SELECT t.name AS name, COUNT(ppt.position_id) AS weight
                FROM tag t
                INNER JOIN position_project_tag ppt ON ppt.tag_id = t.id
                GROUP BY t.id, t.name
                HAVING COUNT(ppt.position_id) > 0
                ORDER BY weight DESC, t.name ASC
            SQL;
        }

        return $conn->fetchAllAssociative($sql);
    }

    /**
     * Resolves every name with one lookup, then inserts only the missing tags.
     *
     * @param list<string> $names
     * @return list<Tag>
     */
    public function resolveAll(array $names): array
    {
        $clean = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name !== '') {
                $clean[mb_strtolower($name)] = $name;
            }
        }
        if ($clean === []) {
            return [];
        }

        $byKey = [];
        foreach ($this->createQueryBuilder('t')
            ->andWhere('LOWER(t.name) IN (:names)')
            ->setParameter('names', array_keys($clean))
            ->getQuery()
            ->getResult() as $tag) {
            $byKey[mb_strtolower($tag->getName())] = $tag;
        }

        $resolved = [];
        foreach ($clean as $key => $name) {
            if (!isset($byKey[$key])) {
                $tag = new Tag();
                $tag->setName($name);
                $this->getEntityManager()->persist($tag);
                $byKey[$key] = $tag;
            }
            $resolved[] = $byKey[$key];
        }

        return $resolved;
    }

    public function findOrCreate(string $name): Tag
    {
        $name = trim($name);
        $key = mb_strtolower($name);
        if (isset($this->pending[$key])) {
            return $this->pending[$key];
        }

        $existing = $this->findOneByName($name);
        if ($existing) {
            return $this->pending[$key] = $existing;
        }

        $tag = new Tag();
        $tag->setName($name);
        $this->getEntityManager()->persist($tag);

        return $this->pending[$key] = $tag;
    }
}
