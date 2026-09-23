<?php

namespace App\Controller;

use App\Entity\DiscussionPost;
use App\Entity\Position;
use App\Entity\User;
use App\Form\DiscussionPostType;
use App\Repository\DiscussionPostRepository;
use App\Security\Voter\PositionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DiscussionController extends AbstractController
{
    #[Route('/positions/{id}/discussion', name: 'discussion_post', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function post(Position $position, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::VIEW, $position);
        $post = new DiscussionPost();
        $form = $this->createForm(DiscussionPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException();
            }
            $post->setPosition($position);
            $post->setAuthor($user);
            $em->persist($post);
            $em->flush();
        }

        return $this->redirectToRoute('position_show', ['id' => $position->getId(), '_fragment' => 'discussion']);
    }

    #[Route('/positions/{id}/discussion/feed', name: 'discussion_feed', methods: ['GET'])]
    public function feed(Position $position, Request $request, DiscussionPostRepository $posts): JsonResponse
    {
        $this->denyAccessUnlessGranted(PositionVoter::VIEW, $position);
        $after = $request->query->getInt('after') ?: null;
        $items = [];
        foreach ($posts->findForPosition($position, $after) as $post) {
            $items[] = [
                'id' => $post->getId(),
                'author' => $post->getAuthor()->getFullName(),
                'authorId' => $post->getAuthor()->getId(),
                'profileUrl' => $this->generateUrl('profile_public', ['id' => $post->getAuthor()->getId()]),
                'content' => $post->getContent(),
                'createdAt' => $post->getCreatedAt()->format('Y-m-d H:i'),
            ];
        }

        return $this->json($items);
    }
}
