<?php

namespace App\Controller;

use App\Entity\AttributeDefinition;
use App\Entity\User;
use App\Repository\AttributeDefinitionRepository;
use App\Repository\AttributeValueRepository;
use App\Repository\CurriculumVitaeRepository;
use App\Repository\ProjectRepository;
use App\Security\Voter\ProfileVoter;
use App\Service\AttributeUsageTracker;
use App\Service\AttributeValueHelper;
use App\Service\CvAssembler;
use App\Service\OptimisticLocker;
use App\Service\PositionAccessEvaluator;
use App\Service\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'profile_me')]
    public function me(): Response
    {
        $user = $this->requireUser();

        return $this->redirectToRoute('profile_show', ['id' => $user->getId()]);
    }

    #[Route('/profile/{id}', name: 'profile_show', requirements: ['id' => '\d+'])]
    public function show(
        User $profile,
        AttributeDefinitionRepository $attributes,
        AttributeValueRepository $values,
        ProjectRepository $projects,
        CurriculumVitaeRepository $cvs,
        PositionAccessEvaluator $access,
    ): Response {
        $actor = $this->getUser();
        $canEdit = $this->isGranted(ProfileVoter::EDIT, $profile);
        if (!$canEdit) {
            if ($actor instanceof User && $actor->isRecruiter()) {
                return $this->redirectToRoute('profile_public', ['id' => $profile->getId()]);
            }

            throw $this->createAccessDeniedException();
        }

        $valueMap = [];
        foreach ($values->findForOwner($profile) as $value) {
            $valueMap[$value->getAttribute()->getId()] = $value;
        }

        $visibleCvs = [];
        foreach ($cvs->findForOwner($profile) as $cv) {
            if (($actor instanceof User && $actor->isAdmin()) || $access->canAccess($cv->getPosition(), $profile, $valueMap)) {
                $visibleCvs[] = $cv;
            }
        }

        return $this->render('profile/show.html.twig', [
            'profile' => $profile,
            'canEdit' => true,
            'isPublicSnippet' => false,
            'builtins' => $attributes->findBuiltins(),
            'values' => $valueMap,
            'library' => $canEdit ? $attributes->findLibrary() : [],
            'recent' => $canEdit && $actor instanceof User ? $attributes->findRecentlyUsed($actor) : [],
            'projects' => $projects->findForOwner($profile),
            'cvs' => $visibleCvs,
        ]);
    }

    #[Route('/users/{id}', name: 'profile_public', requirements: ['id' => '\d+'])]
    public function publicView(User $profile, CurriculumVitaeRepository $cvs, CvAssembler $assembler): Response
    {
        $this->denyAccessUnlessGranted('ROLE_RECRUITER');

        return $this->render('profile/public.html.twig', [
            'profile' => $profile,
            'cvs' => $assembler->visibleToRecruiters($cvs->findForOwner($profile)),
        ]);
    }

    #[Route('/profile/{id}/autosave', name: 'profile_autosave', methods: ['POST'])]
    public function autosave(
        User $profile,
        Request $request,
        AttributeDefinitionRepository $attributes,
        AttributeValueHelper $helper,
        OptimisticLocker $locker,
        EntityManagerInterface $em,
        SearchIndexer $indexer,
        AttributeUsageTracker $usage,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(ProfileVoter::EDIT, $profile);
        $payload = json_decode($request->getContent(), true) ?? [];
        $locker->assertVersion($profile, (int) ($payload['version'] ?? 0));

        $ids = array_map('intval', array_merge(array_keys($payload['values'] ?? []), $payload['remove'] ?? []));
        $definitions = $attributes->findByIds($ids);

        foreach ($payload['values'] ?? [] as $attributeId => $raw) {
            $definition = $definitions[(int) $attributeId] ?? null;
            if (!$definition instanceof AttributeDefinition) {
                continue;
            }
            $error = $helper->validate($definition, is_string($raw) ? $raw : null);
            if ($error) {
                return $this->json(['ok' => false, 'error' => $error, 'attributeId' => $definition->getId()], 422);
            }
            $helper->write($profile, $definition, is_string($raw) ? $raw : null);
            $usage->remember($this->requireUser(), $definition);
        }

        foreach ($payload['remove'] ?? [] as $attributeId) {
            $definition = $definitions[(int) $attributeId] ?? null;
            if ($definition instanceof AttributeDefinition && !$definition->isBuiltin()) {
                $value = $profile->findAttributeValue($definition);
                if ($value) {
                    $em->remove($value);
                    $profile->removeAttributeValue($value);
                }
            }
        }

        $profile->touch();
        $locker->flushSafely();
        $indexer->syncOwner($profile);
        $em->flush();

        return $this->json([
            'ok' => true,
            'version' => $profile->getVersion(),
            'savedAt' => (new \DateTimeImmutable())->format('H:i:s'),
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
