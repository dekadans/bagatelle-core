<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Middleware;

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
    public const string ATTRIBUTE_KEY = 'bagatelle.http.middleware';

    public function __construct(private ContainerInterface $container) {}

    public function handleRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        foreach ($this->getMiddleware($request) as [$middleware, $options]) {
            /** @var MiddlewareInterface $middleware */
            $maybeResponse = $middleware->inbound($request, $options);
            if ($maybeResponse) {
                $event->setResponse($maybeResponse);
            }
        }
    }

    public function handleResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        foreach ($this->getMiddleware($request) as [$middleware, $options]) {
            /** @var MiddlewareInterface $middleware */
            $middleware->outbound($request, $response, $options);
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    private function getMiddleware(Request $request): array
    {
        $routeMiddleware = $request->attributes->get(static::ATTRIBUTE_KEY, []);
        return array_map(fn($class) => [
            $this->container->get($class), $request->attributes->get($class, []),
        ], $routeMiddleware);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'handleRequest',
            KernelEvents::RESPONSE => 'handleResponse',
        ];
    }
}
