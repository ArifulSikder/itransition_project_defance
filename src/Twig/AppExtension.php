<?php

namespace App\Twig;

use App\Entity\User;
use App\Enum\AttributeCategory;
use App\Enum\AttributeType;
use App\Enum\PositionLevel;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function getGlobals(): array
    {
        return [
            'attribute_categories' => AttributeCategory::cases(),
            'attribute_types' => AttributeType::cases(),
            'position_levels' => PositionLevel::cases(),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_recruiter', $this->isRecruiter(...)),
            new TwigFunction('is_admin', $this->isAdmin(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('role_labels', $this->roleLabels(...)),
            new TwigFilter('json_decode', $this->jsonDecode(...)),
        ];
    }

    public function jsonDecode(?string $value): mixed
    {
        return $value ? json_decode($value, true) : null;
    }

    public function isRecruiter(?User $user): bool
    {
        return $user?->isRecruiter() ?? false;
    }

    public function isAdmin(?User $user): bool
    {
        return $user?->isAdmin() ?? false;
    }

    /** @param list<string> $roles */
    public function roleLabels(array $roles): string
    {
        $map = [
            'ROLE_ADMIN' => 'role.admin',
            'ROLE_RECRUITER' => 'role.recruiter',
            'ROLE_CANDIDATE' => 'role.candidate',
        ];
        $labels = [];
        foreach ($roles as $role) {
            if (isset($map[$role])) {
                $labels[] = $this->translator->trans($map[$role]);
            }
        }

        return implode(', ', $labels) ?: $this->translator->trans('role.user');
    }
}
