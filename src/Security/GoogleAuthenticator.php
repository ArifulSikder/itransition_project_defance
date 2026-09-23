<?php

namespace App\Security;

use App\Entity\User;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class GoogleAuthenticator extends SocialAuthenticatorTrait
{
    protected function clientKey(): string
    {
        return 'google';
    }

    protected function checkRoute(): string
    {
        return 'connect_google_check';
    }

    protected function applyIdentity(User $user, ResourceOwnerInterface $owner): void
    {
        if ($owner instanceof GoogleUser) {
            $user->setGoogleId((string) $owner->getId());
            if (!$user->getPhotoUrl()) {
                $user->setPhotoUrl($owner->getAvatar());
            }
        }
    }

    protected function emailFrom(ResourceOwnerInterface $owner): ?string
    {
        return $owner instanceof GoogleUser ? $owner->getEmail() : null;
    }

    protected function namesFrom(ResourceOwnerInterface $owner): array
    {
        if (!$owner instanceof GoogleUser) {
            return [null, null];
        }

        return [$owner->getFirstName(), $owner->getLastName()];
    }
}
