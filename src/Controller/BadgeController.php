<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\Voter\ProfileVoter;
use App\Service\BadgeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BadgeController extends AbstractController
{
    #[Route('/profile/{id}/badges.svg', name: 'profile_badges')]
    public function svg(User $profile, Request $request, BadgeService $badges): Response
    {
        $this->denyAccessUnlessGranted(ProfileVoter::VIEW, $profile);
        $download = $request->query->getBoolean('download');

        return new Response($badges->svg($profile), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="badges.svg"',
        ]);
    }
}
