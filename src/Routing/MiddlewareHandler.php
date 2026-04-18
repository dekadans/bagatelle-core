<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Routing;

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Turns Symfony's native lifecycle events into a simple middleware-esque solution.
 */
readonly class MiddlewareHandler implements EventSubscriberInterface
{
    public function __construct(private ContainerInterface $container) {}

    public function handleRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        foreach ($this->getMiddleware($request) as $middleware) {
            $maybeResponse = $middleware->inbound($request);
            if ($maybeResponse) {
                $event->setResponse($maybeResponse);
            }
        }
    }

    public function handleResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        foreach ($this->getMiddleware($request) as $middleware) {
            $middleware->outbound($request, $response);
        }
    }

    /**
     * @param Request $request
     * @return Middleware[]
     */
    private function getMiddleware(Request $request): array
    {
        return Middleware::resolve($request, $this->container->get(...));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'handleRequest',
            KernelEvents::RESPONSE => 'handleResponse',
        ];
    }
}
