<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class LocaleSubscriber implements EventSubscriberInterface
{
    private const LOCALES = ['en', 'bn'];

    public function __construct(private readonly TokenStorageInterface $tokens)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => [['onKernelRequest', 20]]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        $user = $this->tokens->getToken()?->getUser();
        if ($user instanceof User) {
            $request->getSession()->set('_locale', $this->normalize($user->getLocale()));
            $request->getSession()->set('_theme', $user->getTheme());
        }

        $request->setLocale($this->normalize($request->getSession()->get('_locale', 'en')));
        $request->attributes->set('_theme', $request->getSession()->get('_theme', 'light'));
    }

    private function normalize(mixed $locale): string
    {
        return \is_string($locale) && \in_array($locale, self::LOCALES, true) ? $locale : 'en';
    }
}
