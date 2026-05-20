<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface for implementing logic that runs before and/or after an HTTP request is processed by the controller.
 *
 * The parameter $options will contain parameters sent in the Middleware attribute:
 *    #[Middleware(SomeMiddleware::class, ['option_1' => 'value_1'])]
 */
interface MiddlewareInterface
{
    /**
     * Process the request before it reaches the controller.
     * If a Response is generated and returned by this function then the controller will not be called.
     * Return NULL to send the request along to the controller.
     *
     * @param Request $request
     * @param array $options
     * @return Response|null
     */
    public function inbound(Request $request, array $options): ?Response;

    /**
     * Process the generated response before it is sent to the client.
     *
     * @param Request $request
     * @param Response $response
     * @param array $options
     * @return void
     */
    public function outbound(Request $request, Response $response, array $options): void;
}
