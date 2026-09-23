<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OAuthController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google')]
    public function google(ClientRegistry $registry): Response
    {
        return $registry->getClient('google')->redirect(['email', 'profile']);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function googleCheck(): void
    {
    }

    #[Route('/connect/github', name: 'connect_github')]
    public function github(ClientRegistry $registry): Response
    {
        return $registry->getClient('github')->redirect(['user:email', 'read:user']);
    }

    #[Route('/connect/github/check', name: 'connect_github_check')]
    public function githubCheck(): void
    {
    }
}
