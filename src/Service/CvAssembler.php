<?php

namespace App\Service;

use App\Entity\AttributeDefinition;
use App\Entity\AttributeValue;
use App\Entity\CurriculumVitae;
use App\Entity\Position;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AttributeType;
use App\Repository\AttributeValueRepository;
use App\Repository\ProjectRepository;

class CvAssembler
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly AttributeValueRepository $values,
        private readonly AttributeValueHelper $helper,
        private readonly PositionAccessEvaluator $access,
    ) {
    }

    /**
     * A CV is almost virtual: the row exists, but fields are looked up from the
     * candidate's single AttributeValue per attribute plus filtered projects.
     *
     * @return array{
     *   sections: list<array{title: string, items: list<array{definition: AttributeDefinition, value: ?AttributeValue, raw: ?string, empty: bool, display: string}>}>,
     *   projects: list<Project>,
     *   complete: bool
     * }
     */
    public function assemble(CurriculumVitae $cv): array
    {
        $stored = [];
        foreach ($this->values->findForOwner($cv->getOwner()) as $value) {
            $id = $value->getAttribute()->getId();
            if ($id !== null) {
                $stored[$id] = $value;
            }
        }

        $grouped = [];
        $complete = true;

        foreach ($cv->getPosition()->getTemplateAttributes() as $slot) {
            $definition = $slot->getAttribute();
            $value = $stored[$definition->getId()] ?? null;
            $raw = $value?->getRawValue();
            $empty = !$this->helper->isFilled($raw);
            if ($empty) {
                $complete = false;
            }

            $category = $definition->getCategory()->label();
            $grouped[$category][] = [
                'definition' => $definition,
                'value' => $value,
                'raw' => $raw,
                'empty' => $empty,
                'display' => $this->helper->display($raw, $definition->getType()),
            ];
        }

        $sections = [];
        foreach ($grouped as $title => $items) {
            $sections[] = ['title' => $title, 'items' => $items];
        }

        return [
            'sections' => $sections,
            'projects' => $this->filterProjects($cv->getOwner(), $cv->getPosition()),
            'complete' => $complete,
        ];
    }

    /**
     * Compatible table for one position: columns = template attributes,
     * cells = the candidate's shared values (never a per-CV copy).
     *
     * @param list<CurriculumVitae> $cvs
     * @return array{
     *   columns: list<AttributeDefinition>,
     *   rows: list<array{cv: CurriculumVitae, cells: array<int, array{display: string, sort: string, empty: bool}>}>,
     *   aggregates: array<int, array{avg?: ?float, min?: ?float, max?: ?float, count: int, yes?: int, no?: int}>
     * }
     */
    public function tableForPosition(Position $position, array $cvs): array
    {
        $visible = $this->visibleOnPosition($position, $cvs);
        $columns = [];
        foreach ($position->getTemplateAttributes() as $slot) {
            $columns[] = $slot->getAttribute();
        }

        $byOwner = $this->values->mapByOwnerIds(array_map(
            static fn (CurriculumVitae $cv) => (int) $cv->getOwner()->getId(),
            $visible,
        ));

        $rows = [];
        foreach ($visible as $cv) {
            $byAttribute = [];
            foreach ($byOwner[$cv->getOwner()->getId()] ?? [] as $value) {
                $id = $value->getAttribute()->getId();
                if ($id !== null) {
                    $byAttribute[$id] = $value;
                }
            }

            $cells = [];
            foreach ($columns as $definition) {
                $id = (int) $definition->getId();
                $value = $byAttribute[$id] ?? null;
                $raw = $value?->getRawValue();
                $cells[$id] = [
                    'display' => $this->helper->display($raw, $definition->getType()),
                    'sort' => $this->helper->sortKey($raw, $definition->getType()),
                    'empty' => !$this->helper->isFilled($raw),
                ];
            }

            $rows[] = ['cv' => $cv, 'cells' => $cells];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'aggregates' => $this->aggregatesFromVisible($columns, $visible, $byOwner),
        ];
    }

    /**
     * @param list<AttributeDefinition> $columns
     * @param list<CurriculumVitae> $cvs
     * @param array<int, list<AttributeValue>> $byOwner
     * @return array<int, array{avg?: ?float, min?: ?float, max?: ?float, count: int, yes?: int, no?: int}>
     */
    private function aggregatesFromVisible(array $columns, array $cvs, array $byOwner): array
    {
        $map = [];
        foreach ($columns as $definition) {
            $id = (int) $definition->getId();
            $type = $definition->getType();
            if (!in_array($type, [AttributeType::Numeric, AttributeType::Boolean], true)) {
                continue;
            }
            $nums = [];
            $yes = 0;
            $no = 0;
            foreach ($cvs as $cv) {
                $raw = null;
                foreach ($byOwner[$cv->getOwner()->getId()] ?? [] as $value) {
                    if ($value->getAttribute()->getId() === $id) {
                        $raw = $value->getRawValue();
                        break;
                    }
                }
                if (!$this->helper->isFilled($raw)) {
                    continue;
                }
                if ($type === AttributeType::Numeric) {
                    $nums[] = (float) $raw;
                } elseif (in_array(mb_strtolower((string) $raw), ['1', 'true', 'yes', 'on'], true)) {
                    ++$yes;
                } else {
                    ++$no;
                }
            }
            if ($type === AttributeType::Numeric && $nums !== []) {
                $map[$id] = [
                    'avg' => array_sum($nums) / count($nums),
                    'min' => min($nums),
                    'max' => max($nums),
                    'count' => count($nums),
                ];
            }
            if ($type === AttributeType::Boolean && ($yes + $no) > 0) {
                $map[$id] = ['yes' => $yes, 'no' => $no, 'count' => $yes + $no];
            }
        }

        return $map;
    }

    /**
     * Access-rule mismatches only hide CVs in the UI. The CV row stays.
     *
     * @param list<CurriculumVitae> $cvs
     * @return list<CurriculumVitae>
     */
    public function visibleOnPosition(Position $position, array $cvs): array
    {
        if ($position->isPublicAccess()) {
            return array_values($cvs);
        }

        $byOwner = $this->values->mapByOwnerIds(array_map(
            static fn (CurriculumVitae $cv) => (int) $cv->getOwner()->getId(),
            $cvs,
        ));

        $visible = [];
        foreach ($cvs as $cv) {
            $map = [];
            foreach ($byOwner[$cv->getOwner()->getId()] ?? [] as $value) {
                $map[$value->getAttribute()->getId()] = $value;
            }
            if ($this->access->canAccess($position, $cv->getOwner(), $map)) {
                $visible[] = $cv;
            }
        }

        return $visible;
    }

    /**
     * Recruiters only see published CVs whose owners still match access rules.
     *
     * @param list<CurriculumVitae> $cvs
     * @return list<CurriculumVitae>
     */
    public function visibleToRecruiters(array $cvs): array
    {
        $published = array_values(array_filter($cvs, static fn (CurriculumVitae $cv) => $cv->isPublished()));
        if ($published === []) {
            return [];
        }

        $byOwner = $this->values->mapByOwnerIds(array_map(
            static fn (CurriculumVitae $cv) => (int) $cv->getOwner()->getId(),
            $published,
        ));

        $visible = [];
        foreach ($published as $cv) {
            $map = [];
            foreach ($byOwner[$cv->getOwner()->getId()] ?? [] as $value) {
                $attributeId = $value->getAttribute()->getId();
                if ($attributeId !== null) {
                    $map[$attributeId] = $value;
                }
            }
            if ($this->access->canAccess($cv->getPosition(), $cv->getOwner(), $map)) {
                $visible[] = $cv;
            }
        }

        return $visible;
    }

    /** @return list<Project> */
    private function filterProjects(User $owner, Position $position): array
    {
        $all = $this->projects->findForOwner($owner);
        $wanted = [];
        foreach ($position->getProjectTags() as $tag) {
            $wanted[mb_strtolower($tag->getName())] = true;
        }

        $filtered = [];
        foreach ($all as $project) {
            if ($wanted === []) {
                $filtered[] = $project;
                continue;
            }

            foreach ($project->getTags() as $tag) {
                if (isset($wanted[mb_strtolower($tag->getName())])) {
                    $filtered[] = $project;
                    break;
                }
            }
        }

        return array_slice($filtered, 0, $position->getMaxProjects());
    }
}
