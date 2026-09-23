<?php

namespace App\Service;

use App\Entity\AttributeDefinition;
use App\Entity\AttributeUsage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AttributeUsageTracker
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function remember(User $user, AttributeDefinition $attribute): void
    {
        $usage = $this->em->getRepository(AttributeUsage::class)->findOneBy([
            'user' => $user,
            'attribute' => $attribute,
        ]);

        if (!$usage) {
            $usage = new AttributeUsage();
            $usage->setUser($user);
            $usage->setAttribute($attribute);
            $this->em->persist($usage);
        }

        $usage->touch();
    }
}
