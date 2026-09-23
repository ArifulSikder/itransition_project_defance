<?php

namespace App\Security;

use App\Entity\AttributeDefinition;
use App\Entity\User;
use App\Repository\AttributeDefinitionRepository;
use App\Repository\UserRepository;
use App\Service\AttributeValueHelper;
use App\Service\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

abstract class SocialAuthenticatorTrait extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        protected readonly ClientRegistry $clients,
        protected readonly UserRepository $users,
        protected readonly EntityManagerInterface $em,
        protected readonly RouterInterface $router,
        protected readonly AttributeDefinitionRepository $attributes,
        protected readonly AttributeValueHelper $values,
        protected readonly SearchIndexer $indexer,
    ) {
    }

    abstract protected function clientKey(): string;

    abstract protected function checkRoute(): string;

    abstract protected function applyIdentity(User $user, ResourceOwnerInterface $owner): void;

    abstract protected function emailFrom(ResourceOwnerInterface $owner): ?string;

    abstract protected function namesFrom(ResourceOwnerInterface $owner): array;

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === $this->checkRoute();
    }

    public function authenticate(Request $request): \Symfony\Component\Security\Http\Authenticator\Passport\Passport
    {
        $client = $this->clients->getClient($this->clientKey());
        $accessToken = $this->fetchAccessToken($client);

        return new \Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport(
            new \Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                $owner = $client->fetchUserFromToken($accessToken);
                $email = $this->emailFrom($owner);
                if (!$email) {
                    throw new AuthenticationException('The social account has no email address.');
                }

                $user = $this->users->findOneByEmail($email) ?? (new User())->setEmail($email)->setRoles(['ROLE_CANDIDATE']);
                $user->setEmailVerified(true);
                $user->setConfirmationToken(null);
                $this->applyIdentity($user, $owner);

                [$first, $last] = $this->namesFrom($owner);
                if (!$user->getFirstName() && $first) {
                    $user->setFirstName($first);
                }
                if (!$user->getLastName() && $last) {
                    $user->setLastName($last);
                }

                $this->em->persist($user);
                $this->em->flush();
                $this->syncBuiltins($user);
                $this->indexer->indexUser($user);
                $this->em->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('danger', $exception->getMessageKey());

        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }

    private function syncBuiltins(User $user): void
    {
        $map = [
            AttributeDefinition::BUILTIN_FIRST_NAME => $user->getFirstName(),
            AttributeDefinition::BUILTIN_LAST_NAME => $user->getLastName(),
            AttributeDefinition::BUILTIN_LOCATION => $user->getLocation(),
            AttributeDefinition::BUILTIN_PHOTO => $user->getPhotoUrl(),
        ];

        $byCode = [];
        foreach ($this->attributes->findBuiltins() as $definition) {
            if ($definition->getCode()) {
                $byCode[$definition->getCode()] = $definition;
            }
        }

        foreach ($map as $code => $raw) {
            if (isset($byCode[$code]) && $raw) {
                $this->values->write($user, $byCode[$code], $raw);
            }
        }
    }
}
