<?php

namespace App\Controller;

use App\Entity\AttributeDefinition;
use App\Entity\CurriculumVitae;
use App\Entity\CvLike;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\SearchType;
use App\Repository\CurriculumVitaeRepository;
use App\Repository\PositionRepository;
use App\Security\Voter\CvVoter;
use App\Security\Voter\PositionVoter;
use App\Service\AttributeValueHelper;
use App\Service\CvAssembler;
use App\Service\CvExportService;
use App\Service\OptimisticLocker;
use App\Service\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class CvController extends AbstractController
{
    #[Route('/positions/{id}/cvs/create', name: 'cv_create', methods: ['POST'])]
    public function create(
        Position $position,
        CurriculumVitaeRepository $cvs,
        PositionRepository $positions,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(PositionVoter::CREATE_CV, $position);
        $user = $this->requireUser();
        $full = $positions->findWithTemplate((int) $position->getId()) ?? $position;
        $existing = $cvs->findOneFor($user, $full);
        if ($existing) {
            return $this->redirectToRoute('cv_show', ['id' => $existing->getId()]);
        }

        $cv = (new CurriculumVitae())->setOwner($user)->setPosition($full);
        $em->persist($cv);
        $full->setCvCount($full->getCvCount() + 1);
        $em->flush();

        return $this->redirectToRoute('cv_show', ['id' => $cv->getId()]);
    }

    #[Route('/cvs/{id}', name: 'cv_show', requirements: ['id' => '\d+'])]
    public function show(int $id, CurriculumVitaeRepository $cvs, CvAssembler $assembler, EntityManagerInterface $em): Response
    {
        $cv = $cvs->findWithRelations($id);
        if (!$cv) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(CvVoter::VIEW, $cv);
        $position = $em->getRepository(Position::class)->findWithTemplate((int) $cv->getPosition()->getId()) ?? $cv->getPosition();
        $cv->setPosition($position);

        $liked = false;
        $user = $this->getUser();
        if ($user instanceof User) {
            $liked = (bool) $em->getRepository(CvLike::class)->findOneBy(['cv' => $cv, 'recruiter' => $user]);
        }

        return $this->render('cv/show.html.twig', [
            'cv' => $cv,
            'assembled' => $assembler->assemble($cv),
            'canEdit' => $this->isGranted(CvVoter::EDIT, $cv),
            'canLike' => $this->isGranted(CvVoter::LIKE, $cv),
            'liked' => $liked,
        ]);
    }

    #[Route('/cvs/{id}/save', name: 'cv_save', methods: ['POST'])]
    public function save(
        CurriculumVitae $cv,
        Request $request,
        AttributeValueHelper $helper,
        OptimisticLocker $locker,
        EntityManagerInterface $em,
        SearchIndexer $indexer,
        PositionRepository $positions,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(CvVoter::EDIT, $cv);
        $payload = json_decode($request->getContent(), true) ?? [];
        $owner = $cv->getOwner();
        $locker->assertVersion($owner, (int) ($payload['version'] ?? 0));
        $cv->setPosition($positions->findWithTemplate((int) $cv->getPosition()->getId()) ?? $cv->getPosition());

        $definitions = $em->getRepository(AttributeDefinition::class);
        $byId = $definitions instanceof \App\Repository\AttributeDefinitionRepository
            ? $definitions->findByIds(array_map('intval', array_keys($payload['values'] ?? [])))
            : [];

        foreach ($payload['values'] ?? [] as $attributeId => $raw) {
            $definition = $byId[(int) $attributeId] ?? null;
            if (!$definition instanceof AttributeDefinition || !$cv->usesAttribute($definition)) {
                continue;
            }
            $error = $helper->validate($definition, is_string($raw) ? $raw : null);
            if ($error) {
                return $this->json(['ok' => false, 'error' => $error], 422);
            }
            $helper->write($owner, $definition, is_string($raw) ? $raw : null);
        }

        $owner->touch();
        $cv->touch();
        $locker->flushSafely();
        $indexer->syncOwner($owner);
        $em->flush();

        return $this->json(['ok' => true, 'version' => $owner->getVersion(), 'savedAt' => (new \DateTimeImmutable())->format('H:i:s')]);
    }

    #[Route('/cvs/{id}/publish', name: 'cv_publish', methods: ['POST'])]
    public function publish(
        CurriculumVitae $cv,
        CvAssembler $assembler,
        EntityManagerInterface $em,
        SearchIndexer $indexer,
        PositionRepository $positions,
    ): Response {
        $this->denyAccessUnlessGranted(CvVoter::EDIT, $cv);
        $cv->setPosition($positions->findWithTemplate((int) $cv->getPosition()->getId()) ?? $cv->getPosition());
        $assembled = $assembler->assemble($cv);
        if (!$assembled['complete']) {
            $this->addFlash('danger', 'Fill every attribute before publishing.');

            return $this->redirectToRoute('cv_show', ['id' => $cv->getId()]);
        }

        $cv->setPublished(true);
        $cv->touch();
        $indexer->indexCv($cv);
        $em->flush();
        $this->addFlash('success', 'CV is now visible to recruiters.');

        return $this->redirectToRoute('cv_show', ['id' => $cv->getId()]);
    }

    #[Route('/cvs/{id}/unpublish', name: 'cv_unpublish', methods: ['POST'])]
    public function unpublish(CurriculumVitae $cv, EntityManagerInterface $em, SearchIndexer $indexer): Response
    {
        $this->denyAccessUnlessGranted(CvVoter::EDIT, $cv);
        $cv->setPublished(false);
        $indexer->indexCv($cv);
        $em->flush();

        return $this->redirectToRoute('cv_show', ['id' => $cv->getId()]);
    }

    #[Route('/cvs/{id}/like', name: 'cv_like', methods: ['POST'])]
    public function like(CurriculumVitae $cv, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(CvVoter::LIKE, $cv);
        $user = $this->requireUser();
        $existing = $em->getRepository(CvLike::class)->findOneBy(['cv' => $cv, 'recruiter' => $user]);
        if ($existing) {
            $em->remove($existing);
            $cv->setLikeCount(max(0, $cv->getLikeCount() - 1));
        } else {
            $like = (new CvLike())->setCv($cv)->setRecruiter($user);
            $em->persist($like);
            $cv->setLikeCount($cv->getLikeCount() + 1);
        }
        $em->flush();

        return $this->redirectToRoute('cv_show', ['id' => $cv->getId()]);
    }

    #[Route('/cvs/{id}/print', name: 'cv_print')]
    public function print(CurriculumVitae $cv, CvExportService $export, PositionRepository $positions): Response
    {
        $this->denyAccessUnlessGranted(CvVoter::VIEW, $cv);
        $cv->setPosition($positions->findWithTemplate((int) $cv->getPosition()->getId()) ?? $cv->getPosition());

        return new Response($export->printableHtml($cv));
    }

    #[Route('/cvs/{id}/pdf', name: 'cv_pdf', requirements: ['id' => '\d+'])]
    public function pdf(CurriculumVitae $cv, CvExportService $export, PositionRepository $positions): Response
    {
        $this->denyAccessUnlessGranted(CvVoter::VIEW, $cv);
        $cv->setPosition($positions->findWithTemplate((int) $cv->getPosition()->getId()) ?? $cv->getPosition());

        return new Response($export->pdf($cv), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="cv-'.$cv->getId().'.pdf"',
        ]);
    }

    #[Route('/positions/{id}/export', name: 'cv_export')]
    public function export(Position $position, CvExportService $export, PositionRepository $positions): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('ROLE_RECRUITER');
        $full = $positions->findWithTemplate((int) $position->getId()) ?? $position;
        $path = $export->spreadsheetForPosition($full);
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'position-'.$position->getId().'-cvs.xlsx');
        $response->deleteFileAfterSend();

        return $response;
    }

    #[Route('/cvs/bulk', name: 'cv_bulk', methods: ['POST'])]
    public function bulk(Request $request, CurriculumVitaeRepository $cvs, EntityManagerInterface $em, SearchIndexer $indexer): Response
    {
        $ids = array_map('intval', (array) $request->request->all('ids'));
        $action = $request->request->get('action');
        $owner = $this->requireUser();

        foreach ($cvs->findBy(['id' => $ids]) as $cv) {
            if (!$this->isGranted(CvVoter::EDIT, $cv)) {
                continue;
            }
            if ($action === 'delete') {
                $cv->getPosition()->setCvCount(max(0, $cv->getPosition()->getCvCount() - 1));
                $indexer->remove(SearchType::Cv, (int) $cv->getId());
                $em->remove($cv);
            }
        }
        $em->flush();

        return $this->redirectToRoute('profile_show', ['id' => $owner->isAdmin() ? $owner->getId() : $owner->getId(), '_fragment' => 'cvs']);
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
