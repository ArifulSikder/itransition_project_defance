<?php

namespace App\Service;

use App\Entity\AttributeValue;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\User;
use App\Enum\AttributeType;
use App\Enum\FilterOperator;

class PositionAccessEvaluator
{
    /**
     * A candidate may open a position when it is public, or every access rule matches
     * a value already stored on their profile.
     *
     * @param array<int, AttributeValue> $valuesByAttributeId
     */
    public function canAccess(Position $position, User $user, array $valuesByAttributeId): bool
    {
        if ($user->isAdmin() || $user->isRecruiter()) {
            return true;
        }

        if ($position->isPublicAccess()) {
            return true;
        }

        foreach ($position->getAccessRules() as $rule) {
            $value = $valuesByAttributeId[$rule->getAttribute()->getId()] ?? null;
            if (!$this->matches($rule, $value?->getRawValue())) {
                return false;
            }
        }

        return true;
    }

    public function matches(PositionAccessRule $rule, ?string $raw): bool
    {
        if ($raw === null || $raw === '') {
            return false;
        }

        $expected = $rule->getCompareValue();
        $operator = $rule->getOperator();
        $type = $rule->getAttribute()->getType();

        return match ($operator) {
            FilterOperator::Equals => $this->equals($type, $raw, $expected),
            FilterOperator::Contains => str_contains(mb_strtolower($raw), mb_strtolower($expected)),
            FilterOperator::GreaterThan => (float) $raw > (float) $expected,
            FilterOperator::GreaterOrEqual => (float) $raw >= (float) $expected,
            FilterOperator::LessThan => (float) $raw < (float) $expected,
            FilterOperator::LessOrEqual => (float) $raw <= (float) $expected,
            FilterOperator::Before => $raw < $expected,
            FilterOperator::After => $raw > $expected,
            FilterOperator::Overlaps => $this->periodOverlaps($raw, $expected),
        };
    }

    private function equals(AttributeType $type, string $raw, string $expected): bool
    {
        if ($type === AttributeType::Boolean) {
            return $this->asBool($raw) === $this->asBool($expected);
        }

        if ($type === AttributeType::Numeric) {
            return (float) $raw === (float) $expected;
        }

        return mb_strtolower($raw) === mb_strtolower($expected);
    }

    private function asBool(string $value): bool
    {
        return in_array(mb_strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    private function periodOverlaps(string $raw, string $expected): bool
    {
        $left = json_decode($raw, true);
        $right = json_decode($expected, true);
        if (!is_array($left) || !is_array($right)) {
            return false;
        }

        $fromA = $left['from'] ?? '';
        $toA = $left['to'] ?? '9999-12-31';
        $fromB = $right['from'] ?? '';
        $toB = $right['to'] ?? '9999-12-31';

        return $fromA <= $toB && $fromB <= $toA;
    }
}
