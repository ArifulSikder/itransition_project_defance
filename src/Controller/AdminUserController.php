<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/users')]
class AdminUserController extends AbstractController
{
    #[Route('', name: 'admin_users')]
    public function index(Request $request, UserRepository $users): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $users->searchAdmin((string) $request->query->get('q', '')),
            'q' => $request->query->get('q', ''),
        ]);
    }

    #[Route('/bulk', name: 'admin_users_bulk', methods: ['POST'])]
    public function bulk(Request $request, UserRepository $users, EntityManagerInterface $em): Response
    {
        $ids = array_map('intval', (array) $request->request->all('ids'));
        $action = (string) $request->request->get('action');
        $current = $this->getUser();

        foreach ($users->findBy(['id' => $ids]) as $user) {
            match ($action) {
                'block' => $user->setBlocked(true),
                'unblock' => $user->setBlocked(false),
                'delete' => $current instanceof User && $current->getId() !== $user->getId() ? $em->remove($user) : null,
                'role_candidate' => $user->setRoles(['ROLE_CANDIDATE']),
                'role_recruiter' => $user->setRoles(['ROLE_RECRUITER']),
                'role_admin' => $user->setRoles(['ROLE_ADMIN', 'ROLE_RECRUITER', 'ROLE_CANDIDATE']),
                'remove_admin' => $user->setRoles(array_values(array_diff($user->getRoles(), ['ROLE_ADMIN', 'ROLE_USER']))),
                default => null,
            };
        }

        $em->flush();
        $this->addFlash('success', 'Users updated.');

        return $this->redirectToRoute('admin_users');
    }
}
