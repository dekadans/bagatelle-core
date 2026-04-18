<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Routing;

use Symfony\Component\Routing\Route;

interface RouteDecoratorInterface
{
    public function decorate(Route $route): void;
}
