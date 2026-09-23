<?php

namespace App\Controller;

use App\Entity\AttributeDefinition;
use App\Enum\AttributeCategory;
use App\Repository\AttributeDefinitionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class AttributeApiController extends AbstractController
{
    #[Route('/api/attributes', name: 'attribute_lookup', methods: ['GET'])]
    public function lookup(Request $request, AttributeDefinitionRepository $attributes): JsonResponse
    {
        $category = $request->query->get('category');
        $items = $attributes->findLibrary(
            $request->query->get('q') ?: null,
            $category ? AttributeCategory::from($category) : null,
        );

        return $this->json(array_map(static fn (AttributeDefinition $a) => [
            'id' => $a->getId(),
            'name' => $a->getName(),
            'category' => $a->getCategory()->value,
            'categoryLabel' => $a->getCategory()->label(),
            'type' => $a->getType()->value,
            'typeLabel' => $a->getType()->label(),
            'operators' => array_map(static fn ($op) => ['value' => $op->value, 'label' => $op->label()], $a->getType()->operators()),
            'options' => $a->getOptions(),
            'builtin' => $a->isBuiltin(),
        ], $items));
    }
}
