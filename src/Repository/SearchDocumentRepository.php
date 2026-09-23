<?php

namespace App\Repository;

use App\Entity\SearchDocument;
use App\Enum\SearchType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
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
     * Native full-text search: MySQL MATCH ... AGAINST, or PostgreSQL tsvector.
     * Results are ranked by the engine. This is not a table scan.
     *
     * @return list<array{id:int|string,type:string,entity_id:int|string,title:string,route:string|null,rank:float|string}>
     */
    public function fullTextSearch(string $query, int $limit = 40): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();
        $platform = $conn->getDatabasePlatform();
        $types = ['limit' => ParameterType::INTEGER];

        if ($platform instanceof PostgreSQLPlatform) {
            $sql = <<<SQL
                SELECT sd.id, sd.type, sd.entity_id, sd.title, sd.route,
                       ts_rank(to_tsvector('english', sd.title || ' ' || sd.body), plainto_tsquery('english', :q)) AS rank
                FROM search_document sd
                WHERE to_tsvector('english', sd.title || ' ' || sd.body) @@ plainto_tsquery('english', :q)
                ORDER BY rank DESC, sd.title ASC
                LIMIT :limit
            SQL;

            return $conn->fetchAllAssociative($sql, ['q' => $query, 'limit' => $limit], $types);
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $terms = preg_split('/\s+/u', $query) ?: [];
            $boolean = [];
            foreach ($terms as $term) {
                $term = preg_replace('/[^\p{L}\p{N}_]+/u', '', $term) ?? '';
                if (mb_strlen($term) >= 3) {
                    $boolean[] = '+'.$term.'*';
                }
            }
            if ($boolean === []) {
                return [];
            }

            $sql = <<<SQL
                SELECT sd.id, sd.type, sd.entity_id, sd.title, sd.route,
                       MATCH(sd.title, sd.body) AGAINST (:q IN BOOLEAN MODE) AS rank
                FROM search_document sd
                WHERE MATCH(sd.title, sd.body) AGAINST (:q IN BOOLEAN MODE)
                ORDER BY rank DESC, sd.title ASC
                LIMIT :limit
            SQL;

            return $conn->fetchAllAssociative($sql, ['q' => implode(' ', $boolean), 'limit' => $limit], $types);
        }

        return $conn->fetchAllAssociative(
            'SELECT id, type, entity_id, title, route, 1.0 AS rank
             FROM search_document
             WHERE title LIKE :q OR body LIKE :q
             ORDER BY title ASC
             LIMIT :limit',
            ['q' => $query.'%', 'limit' => $limit],
            $types,
        );
    }
}
