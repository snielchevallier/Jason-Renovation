<?php

namespace App\EventListener;

use App\Exception\AdminGuardException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Transforme une AdminGuardException en message flash + redirection, au lieu
 * de la page d'erreur 500 par defaut. Scope au back-office (/admin) : le
 * firewall /api n'utilise jamais cette exception.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
class AdminGuardExceptionListener
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof AdminGuardException) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }

        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('danger', $exception->getMessage());
        }

        $target = $request->headers->get('referer') ?: $this->urlGenerator->generate('admin');

        $event->setResponse(new RedirectResponse($target));
    }
}
