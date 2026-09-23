<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\AttributeValueRepository;
use App\Repository\PositionRepository;

/**
 * Guests see public positions. Candidates see positions their profile matches.
 * Recruiters and administrators see the shared catalogue.
 */
class PositionVisibility
{
    public function __construct(
        private readonly PositionRepository $positions,
        private readonly AttributeValueRepository $values,
        private readonly PositionAccessEvaluator $access,
    ) {
    }

    /**
     * @return list<int>|null null means do not restrict the query
     */
    public function visibleIds(?User $user): ?array
    {
        if ($user?->isRecruiter()) {
            return null;
        }

        if (!$user instanceof User) {
            return $this->positions->findPublicIds();
        }

        $map = [];
        foreach ($this->values->findForOwner($user) as $value) {
            $attributeId = $value->getAttribute()->getId();
            if ($attributeId !== null) {
                $map[$attributeId] = $value;
            }
        }

        $ids = [];
        foreach ($this->positions->findForAccessCheck() as $position) {
            if ($this->access->canAccess($position, $user, $map)) {
                $ids[] = (int) $position->getId();
            }
        }

        return $ids;
    }
}
