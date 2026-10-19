<?php

declare(strict_types=1);

namespace App\Http\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(KernelEvents::REQUEST, priority: 20)]
final class SameOriginListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || $request->isMethodSafe() || !str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        if ('cross-site' === $request->headers->get('Sec-Fetch-Site')) {
            throw new AccessDeniedHttpException('Cross-site request refused.');
        }

        $origin = $request->headers->get('Origin');

        if (null !== $origin && strtolower($origin) !== strtolower($request->getSchemeAndHttpHost())) {
            throw new AccessDeniedHttpException('Request origin refused.');
        }
    }
}
