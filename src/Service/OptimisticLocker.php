<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OptimisticLocker
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Locks the entity at the version the client last saw. Doctrine throws if another
     * save already incremented that version.
     */
    public function assertVersion(object $entity, int $expectedVersion): void
    {
        try {
            $this->em->lock($entity, \Doctrine\DBAL\LockMode::OPTIMISTIC, $expectedVersion);
        } catch (OptimisticLockException $exception) {
            throw new ConflictHttpException('This record was changed by someone else. Reload and try again.', $exception);
        }
    }

    public function flushSafely(): void
    {
        try {
            $this->em->flush();
        } catch (OptimisticLockException $exception) {
            throw new ConflictHttpException('This record was changed by someone else. Reload and try again.', $exception);
        }
    }
}
