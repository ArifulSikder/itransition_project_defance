<?php

namespace App\Security;

use App\Entity\User;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class GithubAuthenticator extends SocialAuthenticatorTrait
{
    protected function clientKey(): string
    {
        return 'github';
    }

    protected function checkRoute(): string
    {
        return 'connect_github_check';
    }

    protected function applyIdentity(User $user, ResourceOwnerInterface $owner): void
    {
        if ($owner instanceof GithubResourceOwner) {
            $user->setGithubId((string) $owner->getId());
            $data = $owner->toArray();
            if (!$user->getPhotoUrl() && isset($data['avatar_url'])) {
                $user->setPhotoUrl($data['avatar_url']);
            }
        }
    }

    protected function emailFrom(ResourceOwnerInterface $owner): ?string
    {
        return $owner instanceof GithubResourceOwner ? $owner->getEmail() : null;
    }

    protected function namesFrom(ResourceOwnerInterface $owner): array
    {
        if (!$owner instanceof GithubResourceOwner) {
            return [null, null];
        }

        $name = trim((string) ($owner->toArray()['name'] ?? ''));
        if ($name === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $name) ?: [];

        return [$parts[0] ?? null, $parts[1] ?? null];
    }
}
