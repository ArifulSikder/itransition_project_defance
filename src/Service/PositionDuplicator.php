<?php

namespace App\Service;

use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\PositionAttribute;
use Doctrine\ORM\EntityManagerInterface;

class PositionDuplicator
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function duplicate(Position $source): Position
    {
        $copy = new Position();
        $copy->setTitle($source->getTitle().' (copy)');
        $copy->setShortDescription($source->getShortDescription());
        $copy->setCompany($source->getCompany());
        $copy->setLevel($source->getLevel());
        $copy->setPublicAccess($source->isPublicAccess());
        $copy->setMaxProjects($source->getMaxProjects());

        foreach ($source->getTemplateAttributes() as $slot) {
            $item = new PositionAttribute();
            $item->setAttribute($slot->getAttribute());
            $item->setSortOrder($slot->getSortOrder());
            $copy->addTemplateAttribute($item);
        }

        foreach ($source->getAccessRules() as $rule) {
            $clone = new PositionAccessRule();
            $clone->setAttribute($rule->getAttribute());
            $clone->setOperator($rule->getOperator());
            $clone->setCompareValue($rule->getCompareValue());
            $copy->addAccessRule($clone);
        }

        foreach ($source->getProjectTags() as $tag) {
            $copy->addProjectTag($tag);
        }

        $this->em->persist($copy);

        return $copy;
    }
}
