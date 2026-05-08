<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Config;

use tthe\Bagatelle\Auth\AuthenticatorInterface;
use tthe\Bagatelle\Http\BasicAuthHandler;
use tthe\Bagatelle\Http\CorsHandler;
use tthe\Bagatelle\Routing\DecoratedControllerLoader;
use tthe\Bagatelle\Routing\MiddlewareHandler;
use Monolog\Handler\StreamHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Bridge\PsrHttpMessage\ArgumentValueResolver\PsrServerRequestResolver;
use Symfony\Bridge\PsrHttpMessage\EventListener\PsrResponseListener;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\EventListener\ErrorListener as ConsoleErrorListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\BackedEnumValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\EventListener\ErrorListener as HttpErrorListener;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

use function DI\create;
use function DI\get;

class DefaultConfiguration
{
    public static function all(): array
    {
        // Sets all the services and configurations bundled in the base Bagatelle installation.
        return array_merge(
            self::eventDispatcher(),
            self::logging(),
            self::psr7Support(),
            self::templating(),
            self::routing(),
            self::http(),
            self::console(),
            self::middleware()
        );
    }

    public static function eventDispatcher(): array
    {
        return [
            EventDispatcherInterface::class => create(EventDispatcher::class),
            ContractsEventDispatcherInterface::class => get(EventDispatcherInterface::class),
            PsrEventDispatcherInterface::class => get(EventDispatcherInterface::class),
        ];
    }

    public static function logging(): array
    {
        return [
            'bagatelle.http.logger.exceptions' => function (ContainerInterface $c) {
                $exceptions = [
                    \Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class, // 400
                    \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException::class, // 401
                    \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class, // 403
                    \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class, // 404
                    \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class, // 405
                    \Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException::class, // 406
                    \Symfony\Component\HttpKernel\Exception\ConflictHttpException::class, // 409
                    \Symfony\Component\HttpKernel\Exception\GoneHttpException::class, // 410
                    \Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException::class, // 415
                    \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException::class, // 422
                ];

                return array_fill_keys($exceptions, [
                    'log_level' => $c->get('app.http.logger.client-errors'),
                    'status_code' => null,
                    'log_channel' => null,
                ]);
            },
            StreamHandler::class => function (ContainerInterface $c) {
                $streamUri = !empty($_ENV['LOG_STREAM']) ? $_ENV['LOG_STREAM'] : 'php://stderr';
                if (!str_contains($streamUri, '://')) {
                    if ($streamUri[0] !== '/') {
                        $streamUri = $c->get('app.root') . '/' . $streamUri;
                    }
                    $streamUri = 'file://' . $streamUri;
                }
                $logLevel = !empty($_ENV['LOG_LEVEL']) ? $_ENV['LOG_LEVEL'] : 'info';

                return new StreamHandler($streamUri, $logLevel);
            },
            ConsoleHandler::class => create(ConsoleHandler::class),
            LoggerInterface::class => function (ContainerInterface $c) {
                $loggerImplementation = PHP_SAPI === 'cli' ? 'app.console.logger' : 'app.http.logger';
                return $c->get($loggerImplementation);
            },
        ];
    }

    public static function psr7Support(): array
    {
        return [
            ServerRequestFactoryInterface::class => create(Psr17Factory::class),
            StreamFactoryInterface::class => create(Psr17Factory::class),
            UploadedFileFactoryInterface::class => create(Psr17Factory::class),
            ResponseFactoryInterface::class => create(Psr17Factory::class),
            UriFactoryInterface::class => create(Psr17Factory::class),
            HttpMessageFactoryInterface::class => create(PsrHttpFactory::class)
                ->constructor(
                    get(ServerRequestFactoryInterface::class),
                    get(StreamFactoryInterface::class),
                    get(UploadedFileFactoryInterface::class),
                    get(ResponseFactoryInterface::class)
                ),
            HttpFoundationFactoryInterface::class => create(HttpFoundationFactory::class),
            'bagatelle.http.psr-response-resolver' => function (ResponseFactoryInterface $rf, StreamFactoryInterface $sf) {
                /**
                 * Anonymous class that creates a new PSR-7 HTTP Response with an empty stream, using PSR-17 factory interfaces.
                 * Used when type hinting ResponseInterface in controller actions.
                 * (So that you don't have to rely on factories or implementations in your controllers.)
                 */
                return new class ($rf, $sf) implements ValueResolverInterface {
                    public function __construct(
                        private ResponseFactoryInterface $responseFactory,
                        private StreamFactoryInterface   $streamFactory
                    ) {}

                    public function resolve(Request $request, ArgumentMetadata $argument): iterable
                    {
                        if ($argument->getType() == ResponseInterface::class) {
                            yield $this->responseFactory
                                ->createResponse()
                                ->withBody($this->streamFactory->createStream());
                        }
                    }
                };
            },
        ];
    }

    public static function templating(): array
    {
        return [
            Twig::class => function (ContainerInterface $c) {
                if (!empty($_ENV['TWIG_CACHE_DIR'])) {
                    $cacheDir = $c->get('app.root') . '/' . $_ENV['TWIG_CACHE_DIR'];
                }
                $templateDir = $c->get('app.root') . '/src/Templates';
                $options = ['cache' => $cacheDir ?? false];
                return new Twig(new TwigFilesystemLoader($templateDir), $options);
            },
        ];
    }

    public static function routing(): array
    {
        return [
            FileLocatorInterface::class => function (ContainerInterface $c) {
                return new FileLocator($c->get('app.root'));
            },
            RouterInterface::class => function (FileLocatorInterface $fileLocator, ContainerInterface $c) {
                $loader = new AttributeDirectoryLoader($fileLocator, new DecoratedControllerLoader());
                if (!empty($_ENV['ROUTING_CACHE_DIR'])) {
                    $cacheDirectory = $c->get('app.root') . '/' . $_ENV['ROUTING_CACHE_DIR'];
                }
                $options = ['cache_dir' => $cacheDirectory ?? null];
                return new Router($loader, 'src/Controllers', $options);
            },
            UrlGeneratorInterface::class => get(RouterInterface::class),
        ];
    }

    public static function http(): array
    {
        return [
            'bagatelle.http.subscribers' => [
                create(RouterListener::class)
                    ->constructor(
                        get(RouterInterface::class),
                        create(RequestStack::class)
                    ),
                create(HttpErrorListener::class)
                    ->constructor(
                        get('app.http.error-handler'),
                        get(LoggerInterface::class),
                        false,
                        get('bagatelle.http.logger.exceptions')
                    ),
                create(PsrResponseListener::class)
                    ->constructor(
                        get(HttpFoundationFactoryInterface::class)
                    ),
                create(MiddlewareHandler::class)
                    ->constructor(
                        get(ContainerInterface::class)
                    ),
            ],
            ArgumentResolverInterface::class => function (ContainerInterface $c) {
                $enumResolver = $c->get(BackedEnumValueResolver::class);
                $psrRequestResolver = $c->get(PsrServerRequestResolver::class);
                $psrResponseResolver = $c->get('bagatelle.http.psr-response-resolver');
                $resolvers = array_merge(
                    [$enumResolver, $psrRequestResolver, $psrResponseResolver],
                    ArgumentResolver::getDefaultArgumentValueResolvers()
                );
                return new ArgumentResolver(argumentValueResolvers: $resolvers);
            },
        ];
    }

    public static function console(): array
    {
        return [
            CommandLoaderInterface::class => function (ContainerInterface $c) {
                $commandMap = [];
                foreach ($c->get('app.console.commands') as $commandClass) {
                    $commandAttribute = new \ReflectionClass($commandClass)->getAttributes(AsCommand::class);
                    if ($commandAttribute) {
                        $name = $commandAttribute[0]->newInstance()->name;
                        $commandMap[$name] = $commandClass;
                    }
                }
                return new ContainerCommandLoader($c, $commandMap);
            },
            'bagatelle.console.subscribers' => [
                create(ConsoleErrorListener::class)
                    ->constructor(get(LoggerInterface::class)),
                get(ConsoleHandler::class),
            ],
        ];
    }

    public static function middleware(): array
    {
        return [
            // CORS middleware
            'bagatelle.http.middleware.cors' => [],
            CorsHandler::class => create()->constructor(get('bagatelle.http.middleware.cors')),

            // Basic Auth middleware
            'bagatelle.http.middleware.basic-auth.realm' => 'Protected content',
            BasicAuthHandler::class => create()->constructor(
                get('bagatelle.http.middleware.basic-auth.realm'),
                get(AuthenticatorInterface::class)
            ),
        ];
    }
}
