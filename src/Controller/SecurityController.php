<?php

namespace App\Controller;

use App\Entity\AttributeDefinition;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\AttributeDefinitionRepository;
use App\Repository\UserRepository;
use App\Service\AttributeValueHelper;
use App\Service\RegistrationMailer;
use App\Service\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Intercepted by the logout key in security.yaml.');
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        AttributeDefinitionRepository $attributes,
        AttributeValueHelper $values,
        RegistrationMailer $mailer,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles(['ROLE_CANDIDATE']);
            $user->setEmailVerified(false);
            $user->setConfirmationToken(bin2hex(random_bytes(32)));
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $em->persist($user);
            $em->flush();

            $byCode = [];
            foreach ($attributes->findBuiltins() as $definition) {
                if ($definition->getCode()) {
                    $byCode[$definition->getCode()] = $definition;
                }
            }
            foreach ([
                AttributeDefinition::BUILTIN_FIRST_NAME => $user->getFirstName(),
                AttributeDefinition::BUILTIN_LAST_NAME => $user->getLastName(),
            ] as $code => $raw) {
                if (isset($byCode[$code])) {
                    $values->write($user, $byCode[$code], $raw);
                }
            }

            $em->flush();
            $mailer->send($user);

            return $this->render('security/check_email.html.twig', [
                'email' => $user->getEmail(),
                'devLink' => $mailer->showsLinkOnPage() ? $mailer->confirmationUrl($user) : null,
            ]);
        }

        return $this->render('security/register.html.twig', ['form' => $form]);
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email')]
    public function verify(
        string $token,
        UserRepository $users,
        EntityManagerInterface $em,
        Security $security,
        SearchIndexer $indexer,
    ): Response {
        $user = $users->findOneBy(['confirmationToken' => $token]);
        if (!$user instanceof User) {
            $this->addFlash('danger', 'auth.invalid_token');

            return $this->redirectToRoute('app_login');
        }

        $user->setEmailVerified(true);
        $user->setConfirmationToken(null);
        $indexer->indexUser($user);
        $em->flush();
        $security->login($user, 'form_login');
        $this->addFlash('success', 'auth.confirmed');

        return $this->redirectToRoute('profile_me');
    }

    #[Route('/resend-confirmation', name: 'app_resend_confirmation', methods: ['POST'])]
    public function resend(Request $request, UserRepository $users, EntityManagerInterface $em, RegistrationMailer $mailer): Response
    {
        if (!$this->isCsrfTokenValid('resend', (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        $email = trim((string) $request->request->get('email'));
        $user = $email !== '' ? $users->findOneByEmail($email) : null;
        $devLink = null;
        if ($user instanceof User && !$user->isEmailVerified()) {
            if (!$user->getConfirmationToken()) {
                $user->setConfirmationToken(bin2hex(random_bytes(32)));
                $em->flush();
            }
            $mailer->send($user);
            if ($mailer->showsLinkOnPage()) {
                $devLink = $mailer->confirmationUrl($user);
            }
        }

        return $this->render('security/check_email.html.twig', [
            'email' => $email,
            'devLink' => $devLink,
        ]);
    }
}
