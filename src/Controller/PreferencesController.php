<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PreferencesController extends AbstractController
{
    #[Route('/preferences/locale/{locale}', name: 'preferences_locale', requirements: ['locale' => 'en|bn'])]
    public function locale(string $locale, Request $request, EntityManagerInterface $em): Response
    {
        $request->getSession()->set('_locale', $locale);
        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setLocale($locale);
            $em->flush();
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_home'));
    }

    #[Route('/preferences/theme/{theme}', name: 'preferences_theme', requirements: ['theme' => 'light|dark'])]
    public function theme(string $theme, Request $request, EntityManagerInterface $em): Response
    {
        $request->getSession()->set('_theme', $theme);
        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setTheme($theme);
            $em->flush();
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_home'));
    }
}
