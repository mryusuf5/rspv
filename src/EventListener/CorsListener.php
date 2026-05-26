<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsListener
{
    public function __construct(private readonly string $corsAllowOrigin) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 255)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) return;

        $request = $event->getRequest();
        if ($request->getMethod() !== 'OPTIONS') return;

        $response = new Response('', Response::HTTP_NO_CONTENT);
        $this->addHeaders($response);
        $event->setResponse($response);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: 255)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) return;
        $this->addHeaders($event->getResponse());
    }

    private function addHeaders(Response $response): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $this->corsAllowOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept');
        $response->headers->set('Access-Control-Max-Age', '3600');
    }
}
