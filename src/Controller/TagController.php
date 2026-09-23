<?php

namespace App\Controller;

use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class TagController extends AbstractController
{
    #[Route('/tags/suggest', name: 'tag_suggest')]
    public function suggest(Request $request, TagRepository $tags): JsonResponse
    {
        $prefix = trim((string) $request->query->get('q', ''));
        $items = $prefix === '' ? [] : $tags->suggest($prefix);

        return $this->json(array_map(static fn ($tag) => ['value' => $tag->getName()], $items));
    }
}
