<?php

declare(strict_types=1);

namespace tthe\Bagatelle;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\ArgumentResolver\ArgumentResolverInterface as ConsoleArgumentResolverInterface;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface as HttpArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;
use Symfony\Component\HttpKernel\HttpKernel;

/**
 * Bootstraps the application.
 */
class Application
{
    private string $appRoot;
    private ContainerInterface $container;
    private EventDispatcherInterface $dispatcher;
    private LoggerInterface $logger;

    public private(set) \Closure $console {
        get {
            $this->console ??= $this->makeConsoleApplication();
            return $this->console;
        }
    }

    public private(set) \Closure $http {
        get {
            $this->http ??= $this->makeHttpApplication();
            return $this->http;
        }
    }

    public function __construct(string $appRoot)
    {
        $this->appRoot = $appRoot;
    }


    /**
     * Sets up environment variables and the dependency injection container.
     */
    private function boot(): void
    {
        // Load environment variables
        $env = Dotenv::createImmutable($this->appRoot);
        try {
            $env->load();
        } catch (InvalidPathException) {
            echo "No .env file found at $this->appRoot";
            exit();
        }

        foreach (['PATH_CONTAINER', 'PATH_CONTROLLERS', 'PATH_TEMPLATES'] as $var) {
            if (empty($_ENV[$var])) {
                echo "Environment variable '$var' must be set.";
                exit();
            }
        }

        $this->container = require $this->appRoot . '/' . $_ENV['PATH_CONTAINER'];
        $this->dispatcher = $this->service(EventDispatcherInterface::class);
        $this->logger = $this->service(LoggerInterface::class);

        date_default_timezone_set($this->config('app.timezone'));

        set_error_handler(function (int $level, string $message, string $file, int $line) {
            $this->logger->warning("PHP Notice: $message", [
                'level' => $level,
                'file' => $file,
                'line' => $line,
            ]);
            return true;
        });

        // HttpKernel and Console generally catch and report all exceptions and errors.
        // This is for anything happening before or after the main application processing.
        set_exception_handler(function (\Throwable $ex) {
            $this->logger->emergency(
                "Unhandled exception: {$ex->getMessage()}",
                ['exception' => $ex]
            );
            if (PHP_SAPI !== 'cli') {
                http_response_code(500);
                echo 'Internal Server Error';
            }
            exit();
        });
    }

    /**
     * Creates the HTTP Kernel object and returns a closure to execute it.
     */
    private function makeHttpApplication(): \Closure
    {
        $this->boot();
        $this->subscribe('bagatelle.http.subscribers', 'app.http.subscribers');

        $controllerResolver = new ContainerControllerResolver($this->container);
        $argumentResolver = $this->service(HttpArgumentResolverInterface::class);

        $kernel = new HttpKernel(
            $this->dispatcher,
            $controllerResolver,
            new RequestStack(),
            $argumentResolver,
            true
        );

        return function () use ($kernel) {
            $request = Request::createFromGlobals();
            $response = $kernel->handle($request);
            $response->prepare($request);
            $response->send();
            $kernel->terminate($request, $response);
        };
    }

    /**
     * Creates the console application handler and returns a closure to execute it.
     */
    private function makeConsoleApplication(): \Closure
    {
        $this->boot();
        $this->subscribe('bagatelle.console.subscribers', 'app.console.subscribers');

        $name = $this->config('app.console.name');
        $loader = $this->service(CommandLoaderInterface::class);
        $argumentResolver = $this->service(ConsoleArgumentResolverInterface::class);

        $app = new ConsoleApplication($name);
        $app->setCommandLoader($loader);
        $app->setDispatcher($this->dispatcher);
        $app->setArgumentResolver($argumentResolver);

        return function () use ($app) {
            $app->run();
        };
    }

    /**
     * Resolves EventSubscriber instances from one or many container keys and subscribes them to the event dispatcher.
     */
    private function subscribe(...$containerKeys): void
    {
        $subscribers = array_merge(
            ...array_map($this->container->get(...), $containerKeys)
        );

        foreach ($subscribers as $subscriber) {
            $this->dispatcher->addSubscriber($subscriber);
        }
    }

    /**
     * @template T
     * @param class-string<T> $name
     * @return T
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function service(string $name)
    {
        $service = $this->container->get($name);
        if (!$service instanceof $name) {
            throw new \RuntimeException("Container entry $name was not an instance of the key name.");
        }

        return $service;
    }

    /**
     * @param string $name
     * @return string
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function config(string $name): string
    {
        $value = $this->container->get($name);
        if (!is_string($value)) {
            throw new \RuntimeException("Container entry $name must be a string.");
        }

        return $value;
    }
}
