<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Form\ProjectType;
use App\Repository\TagRepository;
use App\Security\Voter\ProfileVoter;
use App\Service\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProjectController extends AbstractController
{
    #[Route('/profile/{id}/projects/new', name: 'project_new')]
    public function new(User $profile, Request $request, EntityManagerInterface $em, TagRepository $tags, SearchIndexer $indexer): Response
    {
        $this->denyAccessUnlessGranted(ProfileVoter::EDIT, $profile);
        $project = (new Project())->setOwner($profile);

        return $this->handle($project, $profile, $request, $em, $tags, $indexer);
    }

    #[Route('/projects/{id}/edit', name: 'project_edit')]
    public function edit(Project $project, Request $request, EntityManagerInterface $em, TagRepository $tags, SearchIndexer $indexer): Response
    {
        $this->denyAccessUnlessGranted(ProfileVoter::EDIT, $project->getOwner());

        return $this->handle($project, $project->getOwner(), $request, $em, $tags, $indexer);
    }

    #[Route('/projects/{id}/delete', name: 'project_delete', methods: ['POST'])]
    public function delete(Project $project, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(ProfileVoter::EDIT, $project->getOwner());
        $ownerId = $project->getOwner()->getId();
        $em->remove($project);
        $em->flush();
        $this->addFlash('success', 'Project removed.');

        return $this->redirectToRoute('profile_show', ['id' => $ownerId, '_fragment' => 'projects']);
    }

    private function handle(
        Project $project,
        User $profile,
        Request $request,
        EntityManagerInterface $em,
        TagRepository $tags,
        SearchIndexer $indexer,
    ): Response {
        $form = $this->createForm(ProjectType::class, $project);
        $form->get('tagsJson')->setData(json_encode(array_map(
            static fn ($tag) => $tag->getName(),
            $project->getTags()->toArray(),
        )));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->clearTags();
            $names = [];
            foreach (json_decode((string) $form->get('tagsJson')->getData(), true) ?? [] as $name) {
                if (is_string($name) && trim($name) !== '') {
                    $names[] = $name;
                }
            }
            foreach ($tags->resolveAll($names) as $tag) {
                $project->addTag($tag);
            }
            $em->persist($project);
            $em->flush();
            $indexer->syncOwner($profile);
            $em->flush();
            $this->addFlash('success', 'Project saved.');

            return $this->redirectToRoute('profile_show', ['id' => $profile->getId(), '_fragment' => 'projects']);
        }

        return $this->render('project/form.html.twig', [
            'form' => $form,
            'project' => $project,
            'profile' => $profile,
        ]);
    }
}
