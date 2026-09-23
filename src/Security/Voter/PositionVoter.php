<?php

namespace App\Security\Voter;

use App\Entity\Position;
use App\Entity\User;
use App\Repository\AttributeValueRepository;
use App\Service\PositionAccessEvaluator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PositionVoter extends Voter
{
    public const VIEW = 'POSITION_VIEW';
    public const MANAGE = 'POSITION_MANAGE';
    public const CREATE_CV = 'POSITION_CREATE_CV';

    public function __construct(
        private readonly PositionAccessEvaluator $access,
        private readonly AttributeValueRepository $values,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE, self::CREATE_CV], true)
            && $subject instanceof Position;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$subject instanceof Position) {
            return false;
        }

        if ($attribute === self::VIEW) {
            return $this->canView($subject, $user instanceof User ? $user : null);
        }

        if (!$user instanceof User) {
            return false;
        }

        if ($attribute === self::MANAGE) {
            return $user->isRecruiter();
        }

        if (!$user->isCandidate()) {
            return false;
        }

        return $this->access->canAccess($subject, $user, $this->valueMap($user));
    }

    private function canView(Position $position, ?User $user): bool
    {
        if ($user?->isRecruiter()) {
            return true;
        }

        if (!$user instanceof User) {
            return $position->isPublicAccess();
        }

        return $this->access->canAccess($position, $user, $this->valueMap($user));
    }

    /** @return array<int, \App\Entity\AttributeValue> */
    private function valueMap(User $user): array
    {
        $map = [];
        foreach ($this->values->findForOwner($user) as $value) {
            $attributeId = $value->getAttribute()->getId();
            if ($attributeId !== null) {
                $map[$attributeId] = $value;
            }
        }

        return $map;
    }
}
