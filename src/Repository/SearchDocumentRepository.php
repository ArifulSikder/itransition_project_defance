<?php

namespace App\Repository;

use App\Entity\SearchDocument;
use App\Enum\SearchType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SearchDocument>
 */
class SearchDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SearchDocument::class);
    }

    public function findOneByTarget(SearchType $type, int $entityId): ?SearchDocument
    {
        return $this->findOneBy(['type' => $type, 'entityId' => $entityId]);
    }

    /**
     * @param list<int> $entityIds
     * @return array<int, SearchDocument>
     */
    public function findForTargets(SearchType $type, array $entityIds): array
    {
        if ($entityIds === []) {
            return [];
        }

        $map = [];
        foreach ($this->createQueryBuilder('s')
            ->andWhere('s.type = :type')
            ->andWhere('s.entityId IN (:ids)')
            ->setParameter('type', $type)
            ->setParameter('ids', $entityIds)
            ->getQuery()
            ->getResult() as $document) {
            $map[$document->getEntityId()] = $document;
        }

        return $map;
    }

    /**
     * MySQL search through Doctrine. Each word must appear in the title or body.
     * Title matches rank above body-only matches.
     *
     * @return list<array{id: int, type: string, entity_id: int, title: string, route: string|null, rank: float}>
     */
    public function fullTextSearch(string $query, int $limit = 40): array
    {
        $query = trim($query);
        $terms = preg_split('/\s+/u', $query) ?: [];
        $terms = array_values(array_filter(
            $terms,
            static fn (string $term): bool => $term !== '',
        ));
        if ($terms === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('s');
        foreach ($terms as $index => $term) {
            $parameter = 'term'.$index;
            $qb->andWhere(sprintf('LOWER(s.title) LIKE :%1$s OR LOWER(s.body) LIKE :%1$s', $parameter))
                ->setParameter($parameter, '%'.mb_strtolower($term).'%');
        }

        /** @var list<SearchDocument> $documents */
        $documents = $qb
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $needle = mb_strtolower($query);
        $hits = [];
        foreach ($documents as $document) {
            $hits[] = [
                'id' => (int) $document->getId(),
                'type' => $document->getType()->value,
                'entity_id' => $document->getEntityId(),
                'title' => $document->getTitle(),
                'route' => $document->getRoute(),
                'rank' => str_contains(mb_strtolower($document->getTitle()), $needle) ? 2.0 : 1.0,
            ];
        }

        usort($hits, static function (array $left, array $right): int {
            return $right['rank'] <=> $left['rank'] ?: strcmp($left['title'], $right['title']);
        });

        return $hits;
    }
}
