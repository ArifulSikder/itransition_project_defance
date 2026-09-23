<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\CurriculumVitaeRepository;
use App\Repository\ProjectRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class BadgeService
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly CurriculumVitaeRepository $cvs,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** @return list<array{code: string, label: string, earned: bool}> */
    public function forUser(User $user): array
    {
        $projectCount = $this->projects->countForOwner($user);
        $stats = $this->cvs->achievementStats($user);

        return [
            ['code' => 'projects10', 'label' => $this->translator->trans('badge.projects', ['%count%' => 10]), 'earned' => $projectCount >= 10],
            ['code' => 'cvs5', 'label' => $this->translator->trans('badge.cvs', ['%count%' => 5]), 'earned' => $stats['total'] >= 5],
            ['code' => 'likes25', 'label' => $this->translator->trans('badge.likes', ['%count%' => 25]), 'earned' => $stats['likes'] >= 25],
            ['code' => 'first', 'label' => $this->translator->trans('badge.first'), 'earned' => $stats['total'] >= 1],
            ['code' => 'published', 'label' => $this->translator->trans('badge.published'), 'earned' => $stats['published'] >= 1],
        ];
    }

    public function svg(User $user): string
    {
        $badges = $this->forUser($user);
        $cardWidth = 168;
        $width = 24 + (count($badges) * ($cardWidth + 12));
        $boxes = '';
        $x = 16;
        foreach ($badges as $badge) {
            $fill = $badge['earned'] ? '#0d6efd' : '#495057';
            $mark = $badge['earned'] ? '★' : '·';
            $boxes .= sprintf(
                '<g><rect x="%d" y="52" rx="10" width="%d" height="44" fill="%s"/><text x="%d" y="79" fill="#fff" font-size="13" font-family="DejaVu Sans, Noto Sans Bengali, sans-serif" text-anchor="middle">%s %s</text></g>',
                $x,
                $cardWidth,
                $fill,
                $x + (int) ($cardWidth / 2),
                $mark,
                htmlspecialchars($badge['label'], ENT_QUOTES),
            );
            $x += $cardWidth + 12;
        }

        $name = $this->escape($user->getFullName());

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="116" viewBox="0 0 {$width} 116">
  <rect width="{$width}" height="116" rx="14" fill="#212529"/>
  <text x="16" y="32" fill="#f8f9fa" font-size="16" font-family="DejaVu Sans, Noto Sans Bengali, sans-serif">{$name} — CV Hub</text>
  {$boxes}
</svg>
SVG;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }
}
