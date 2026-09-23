<?php

namespace App\Security\Voter;

use App\Entity\CurriculumVitae;
use App\Entity\User;
use App\Repository\AttributeValueRepository;
use App\Service\PositionAccessEvaluator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CvVoter extends Voter
{
    public const VIEW = 'CV_VIEW';
    public const EDIT = 'CV_EDIT';
    public const LIKE = 'CV_LIKE';

    public function __construct(
        private readonly PositionAccessEvaluator $access,
        private readonly AttributeValueRepository $values,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::LIKE], true)
            && $subject instanceof CurriculumVitae;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof CurriculumVitae) {
            return false;
        }

        $isOwner = $user->getId() === $subject->getOwner()->getId();

        return match ($attribute) {
            self::EDIT => $user->isAdmin() || $isOwner,
            self::LIKE => $user->isRecruiter() && !$isOwner,
            self::VIEW => $user->isAdmin()
                || ($isOwner && $this->ownerStillMatches($subject))
                || ($user->isRecruiter() && !$isOwner && $subject->isPublished() && $this->ownerStillMatches($subject)),
            default => false,
        };
    }

    private function ownerStillMatches(CurriculumVitae $cv): bool
    {
        $map = [];
        foreach ($this->values->findForOwner($cv->getOwner()) as $value) {
            $map[$value->getAttribute()->getId()] = $value;
        }

        return $this->access->canAccess($cv->getPosition(), $cv->getOwner(), $map);
    }
}
