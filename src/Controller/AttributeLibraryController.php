<?php

namespace App\Controller;

use App\Entity\AttributeDefinition;
use App\Entity\User;
use App\Enum\AttributeCategory;
use App\Form\AttributeDefinitionType;
use App\Repository\AttributeDefinitionRepository;
use App\Service\OptimisticLocker;
use App\Service\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_RECRUITER')]
#[Route('/attributes')]
class AttributeLibraryController extends AbstractController
{
    #[Route('', name: 'attribute_index')]
    public function index(Request $request, AttributeDefinitionRepository $attributes): Response
    {
        $prefix = $request->query->get('q');
        $category = $request->query->get('category');

        return $this->render('attribute/index.html.twig', [
            'attributes' => $attributes->findLibrary(
                $prefix ?: null,
                $category ? AttributeCategory::from($category) : null,
            ),
            'recent' => $attributes->findRecentlyUsed($this->requireUser()),
            'q' => $prefix,
            'category' => $category,
        ]);
    }

    #[Route('/new', name: 'attribute_new')]
    public function new(Request $request, EntityManagerInterface $em, SearchIndexer $indexer): Response
    {
        return $this->handleForm(new AttributeDefinition(), $request, $em, $indexer);
    }

    #[Route('/{id}/edit', name: 'attribute_edit')]
    public function edit(AttributeDefinition $attribute, Request $request, EntityManagerInterface $em, SearchIndexer $indexer, OptimisticLocker $locker): Response
    {
        if ($attribute->isBuiltin() && $request->isMethod('POST')) {
            $this->addFlash('warning', 'Built-in attributes cannot be changed.');

            return $this->redirectToRoute('attribute_index');
        }

        return $this->handleForm($attribute, $request, $em, $indexer, $locker);
    }

    #[Route('/bulk', name: 'attribute_bulk', methods: ['POST'])]
    public function bulk(Request $request, AttributeDefinitionRepository $attributes, EntityManagerInterface $em, SearchIndexer $indexer): Response
    {
        $ids = array_map('intval', (array) $request->request->all('ids'));
        $action = $request->request->get('action');

        foreach ($attributes->findBy(['id' => $ids]) as $attribute) {
            if ($action === 'delete' && !$attribute->isBuiltin()) {
                $indexer->remove(\App\Enum\SearchType::Attribute, (int) $attribute->getId());
                $em->remove($attribute);
            }
        }
        $em->flush();
        $this->addFlash('success', 'Library updated.');

        return $this->redirectToRoute('attribute_index');
    }

    private function handleForm(
        AttributeDefinition $attribute,
        Request $request,
        EntityManagerInterface $em,
        SearchIndexer $indexer,
        ?OptimisticLocker $locker = null,
    ): Response {
        $form = $this->createForm(AttributeDefinitionType::class, $attribute);
        $form->get('optionsText')->setData(implode(', ', $attribute->getOptions()));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($locker && $attribute->getId()) {
                $locker->assertVersion($attribute, (int) $request->request->get('version', $attribute->getVersion()));
            }
            $options = array_values(array_filter(array_map('trim', explode(',', (string) $form->get('optionsText')->getData()))));
            $attribute->setOptions($options ?: null);
            $attribute->touch();
            $em->persist($attribute);
            $em->flush();
            $indexer->indexAttribute($attribute);
            $em->flush();
            $this->addFlash('success', 'Attribute saved.');

            return $this->redirectToRoute('attribute_index');
        }

        return $this->render('attribute/form.html.twig', [
            'form' => $form,
            'attribute' => $attribute,
        ]);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
