<?php

namespace App\Controller;

use App\Entity\DiscussionPost;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\PositionAttribute;
use App\Entity\User;
use App\Enum\FilterOperator;
use App\Enum\SearchType;
use App\Form\DiscussionPostType;
use App\Form\PositionType;
use App\Repository\CurriculumVitaeRepository;
use App\Repository\PositionRepository;
use App\Repository\TagRepository;
use App\Security\Voter\PositionVoter;
use App\Repository\AttributeDefinitionRepository;
use App\Service\CvAssembler;
use App\Service\OptimisticLocker;
use App\Service\PositionDuplicator;
use App\Service\PositionVisibility;
use App\Service\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/positions')]
class PositionController extends AbstractController
{
    #[Route('', name: 'position_index')]
    public function index(Request $request, PositionRepository $positions, PaginatorInterface $paginator, PositionVisibility $visibility): Response
    {
        $user = $this->getUser();
        $query = $positions->listQuery(
            $request->query->get('level'),
            $request->query->get('company'),
            $request->query->get('tag'),
            $visibility->visibleIds($user instanceof User ? $user : null),
        );

        return $this->render('position/index.html.twig', [
            'pagination' => $paginator->paginate($query, $request->query->getInt('page', 1), 12),
            'level' => $request->query->get('level'),
            'company' => $request->query->get('company'),
            'tag' => $request->query->get('tag'),
        ]);
    }

    #[Route('/new', name: 'position_new')]
    public function new(Request $request, EntityManagerInterface $em, TagRepository $tags, SearchIndexer $indexer, OptimisticLocker $locker, AttributeDefinitionRepository $attributes): Response
    {
        $this->denyAccessUnlessGranted('ROLE_RECRUITER');

        return $this->handleForm(new Position(), $request, $em, $tags, $indexer, $locker, $attributes);
    }

    #[Route('/{id}', name: 'position_show', requirements: ['id' => '\d+'])]
    public function show(
        Position $position,
        CurriculumVitaeRepository $cvs,
        PositionRepository $positions,
        CvAssembler $assembler,
    ): Response {
        $full = $positions->findWithTemplate((int) $position->getId()) ?? $position;
        $this->denyAccessUnlessGranted(PositionVoter::VIEW, $full);
        $user = $this->getUser();
        $canCreateCv = $this->isGranted(PositionVoter::CREATE_CV, $full);
        $ownCv = $user instanceof User ? $cvs->findOneFor($user, $full) : null;
        if ($ownCv && $user instanceof User && !$user->isRecruiter() && !$user->isAdmin() && !$canCreateCv) {
            $ownCv = null;
        }
        $published = ($user instanceof User && $user->isRecruiter()) ? $cvs->findPublishedForPosition($full) : [];

        return $this->render('position/show.html.twig', [
            'position' => $full,
            'cvs' => $published,
            'cvTable' => $assembler->tableForPosition($full, $published),
            'ownCv' => $ownCv,
            'canCreateCv' => $canCreateCv,
            'discussionForm' => $this->createForm(DiscussionPostType::class, new DiscussionPost())->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'position_edit', requirements: ['id' => '\d+'])]
    public function edit(Position $position, Request $request, EntityManagerInterface $em, TagRepository $tags, SearchIndexer $indexer, OptimisticLocker $locker, PositionRepository $positions, AttributeDefinitionRepository $attributes): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::MANAGE, $position);
        $full = $positions->findWithTemplate((int) $position->getId()) ?? $position;

        return $this->handleForm($full, $request, $em, $tags, $indexer, $locker, $attributes);
    }

    #[Route('/{id}/duplicate', name: 'position_duplicate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function duplicate(Position $position, PositionDuplicator $duplicator, EntityManagerInterface $em, SearchIndexer $indexer, PositionRepository $positions): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::MANAGE, $position);
        $full = $positions->findWithTemplate((int) $position->getId()) ?? $position;
        $copy = $duplicator->duplicate($full);
        $em->flush();
        $indexer->indexPosition($copy);
        $em->flush();
        $this->addFlash('success', 'Position duplicated.');

        return $this->redirectToRoute('position_edit', ['id' => $copy->getId()]);
    }

    #[Route('/bulk', name: 'position_bulk', methods: ['POST'])]
    public function bulk(Request $request, PositionRepository $positions, EntityManagerInterface $em, SearchIndexer $indexer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_RECRUITER');
        $ids = array_map('intval', (array) $request->request->all('ids'));
        $action = $request->request->get('action');

        foreach ($positions->findBy(['id' => $ids]) as $position) {
            if ($action === 'delete') {
                $indexer->remove(SearchType::Position, (int) $position->getId());
                $em->remove($position);
            }
        }
        $em->flush();
        $this->addFlash('success', 'Selected positions updated.');

        return $this->redirectToRoute('position_index');
    }

    private function handleForm(
        Position $position,
        Request $request,
        EntityManagerInterface $em,
        TagRepository $tags,
        SearchIndexer $indexer,
        OptimisticLocker $locker,
        AttributeDefinitionRepository $attributes,
    ): Response {
        $form = $this->createForm(PositionType::class, $position);
        $form->get('expectedVersion')->setData((string) $position->getVersion());
        $form->get('templateJson')->setData(json_encode(array_map(
            static fn (PositionAttribute $slot) => $slot->getAttribute()->getId(),
            $position->getTemplateAttributes()->toArray(),
        )));
        $form->get('rulesJson')->setData(json_encode(array_map(static fn (PositionAccessRule $rule) => [
            'attributeId' => $rule->getAttribute()->getId(),
            'operator' => $rule->getOperator()->value,
            'value' => $rule->getCompareValue(),
        ], $position->getAccessRules()->toArray())));
        $form->get('tagsJson')->setData(json_encode(array_map(
            static fn ($tag) => $tag->getName(),
            $position->getProjectTags()->toArray(),
        )));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($position->getId()) {
                $locker->assertVersion($position, (int) $form->get('expectedVersion')->getData());
            }

            $this->applyTemplate($position, (string) $form->get('templateJson')->getData(), $em, $attributes);
            $this->applyRules($position, (string) $form->get('rulesJson')->getData(), $attributes);
            $position->clearProjectTags();
            $tagNames = [];
            foreach (json_decode((string) $form->get('tagsJson')->getData(), true) ?? [] as $name) {
                if (is_string($name) && trim($name) !== '') {
                    $tagNames[] = $name;
                }
            }
            foreach ($tags->resolveAll($tagNames) as $tag) {
                $position->addProjectTag($tag);
            }
            $position->touch();
            $em->persist($position);
            $locker->flushSafely();
            $indexer->indexPosition($position);
            $em->flush();
            $this->addFlash('success', 'Position saved.');

            return $this->redirectToRoute('position_show', ['id' => $position->getId()]);
        }

        return $this->render('position/form.html.twig', [
            'form' => $form,
            'position' => $position,
        ]);
    }

    private function applyTemplate(Position $position, string $json, EntityManagerInterface $em, AttributeDefinitionRepository $attributes): void
    {
        $ids = array_values(array_unique(array_map('intval', json_decode($json, true) ?? [])));
        $keep = [];
        foreach ($ids as $id) {
            if ($id > 0) {
                $keep[$id] = true;
            }
        }

        foreach ($position->getTemplateAttributes()->toArray() as $slot) {
            $attributeId = $slot->getAttribute()->getId();
            if ($attributeId === null || !isset($keep[$attributeId])) {
                $position->getTemplateAttributes()->removeElement($slot);
                $em->remove($slot);
            }
        }

        $present = [];
        foreach ($position->getTemplateAttributes() as $slot) {
            $present[$slot->getAttribute()->getId()] = $slot;
        }

        $definitions = $attributes->findByIds($ids);
        $order = 0;
        foreach ($ids as $id) {
            $slot = $present[$id] ?? null;
            if ($slot) {
                $slot->setSortOrder($order++);
                continue;
            }
            $definition = $definitions[$id] ?? null;
            if (!$definition) {
                continue;
            }
            $slot = new PositionAttribute();
            $slot->setAttribute($definition);
            $slot->setSortOrder($order++);
            $position->addTemplateAttribute($slot);
        }
    }

    private function applyRules(Position $position, string $json, AttributeDefinitionRepository $attributes): void
    {
        $rows = json_decode($json, true) ?? [];
        $definitions = $attributes->findByIds(array_map(static fn ($row) => (int) ($row['attributeId'] ?? 0), $rows));
        $position->clearAccessRules();
        foreach ($rows as $row) {
            $definition = $definitions[(int) ($row['attributeId'] ?? 0)] ?? null;
            if (!$definition) {
                continue;
            }
            $rule = new PositionAccessRule();
            $rule->setAttribute($definition);
            $rule->setOperator(FilterOperator::from((string) ($row['operator'] ?? 'eq')));
            $rule->setCompareValue((string) ($row['value'] ?? ''));
            $position->addAccessRule($rule);
        }
    }
}
