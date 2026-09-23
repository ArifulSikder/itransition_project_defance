<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Optimistic locking is cross-cutting: the version travels with the request,
 * and the UI must get a retry/reload message instead of a generic 500.
 */
class OptimisticLockSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof ConflictHttpException) {
            return;
        }

        $request = $event->getRequest();
        $message = $this->translator->trans('lock.conflict');

        if ($this->wantsJson($request)) {
            $event->setResponse(new JsonResponse([
                'ok' => false,
                'conflict' => true,
                'error' => $message,
            ], 409));

            return;
        }

        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('warning', $message);
        }

        $target = $request->headers->get('Referer') ?: '/';
        $event->setResponse(new RedirectResponse($target));
    }

    private function wantsJson(\Symfony\Component\HttpFoundation\Request $request): bool
    {
        $contentType = (string) $request->headers->get('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        return in_array('application/json', $request->getAcceptableContentTypes(), true);
    }
}
