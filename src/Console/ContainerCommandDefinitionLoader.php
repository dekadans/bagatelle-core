<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Console;

use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Console\Attribute\AsCommand;

class ContainerCommandDefinitionLoader implements CommandDefinitionLoaderInterface
{
    private FileLocatorInterface $locator;
    private string $directory;
    private array $options = [];
    private ConfigCacheFactoryInterface $cacheFactory;

    public function __construct(
        FileLocatorInterface $locator,
        string $directory,
        array $options
    ) {
        $this->locator = $locator;
        $this->directory = $directory;
        $this->setOptions($options);
        $this->cacheFactory = new ConfigCacheFactory($this->options['debug']);
    }

    /**
     * Loads available console commands either from cache or by dynamically scanning a given directory.
     * @return array
     */
    public function load(): array
    {
        if ($this->options['cache']) {
            $cache = $this->cacheFactory->cache(
                $this->options['cache'] . '/command_map.php',
                function (ConfigCacheInterface $cache) {
                    $cacheMap = var_export($this->getCommandMap(), return: true);
                    $cacheContent = <<<EOF
                        <?php
                        return $cacheMap;
                        EOF;

                    $cache->write($cacheContent);
                }
            );
            return require $cache->getPath();
        } else {
            return $this->getCommandMap();
        }
    }

    private function setOptions(array $options): void
    {
        $defaults = [
            'extra' => [],
            'cache' => null,
            'debug' => false,
        ];

        $this->options = array_merge($defaults, $options);
    }

    /**
     * Creates the list of commands as key-value pairs: command => class
     * @return array
     */
    private function getCommandMap(): array
    {
        $classes = array_merge(
            $this->findCommandsInDirectory(),
            $this->options['extra']
        );

        $commands = [];
        foreach ($classes as $class) {
            $command = $this->findCommand($class);
            if ($command) {
                $commands[$command] = $class;
            }
        }

        return $commands;
    }

    /**
     * Recursively finds PHP classes in a directory.
     * @return array
     */
    private function findCommandsInDirectory(): array
    {
        $directory = $this->locator->locate($this->directory);
        /** @var iterable<\SplFileInfo> $files */
        $files = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                static fn(\SplFileInfo $f) => $f->getExtension() === 'php' || $f->isDir()
            )
        );

        $classes = array_filter(
            array_map($this->findClass(...), iterator_to_array($files))
        );
        return array_values($classes);
    }

    /**
     * Returns (the first) qualified name of a defined class in the given file.
     * @param string $path
     * @return string|null
     */
    private function findClass(string $path): ?string
    {
        $qualifiedName = '';
        $section = null;
        $phpTokens = token_get_all(file_get_contents($path));

        for ($i = 0; $i < count($phpTokens); $i++) {
            if (!is_array($phpTokens[$i])) {
                continue;
            }

            [$token, $value] = $phpTokens[$i];

            if ($section === T_NAMESPACE && $token === T_NAME_QUALIFIED) {
                $qualifiedName .= $value;
            } elseif ($section === T_CLASS && $token === T_STRING) {
                return $qualifiedName . '\\' . $value;
            }

            $section = match ($token) {
                T_NAMESPACE, T_CLASS => $token,
                T_WHITESPACE => $section,
                default => null
            };
        }

        return null;
    }

    /**
     * Returns the name of a command, as defined by the AsCommand attribute.
     * @param string $className
     * @return string|null
     * @throws \ReflectionException
     */
    private function findCommand(string $className): ?string
    {
        $attributes = new \ReflectionClass($className)->getAttributes(
            AsCommand::class,
            \ReflectionAttribute::IS_INSTANCEOF
        );
        return $attributes ? $attributes[0]->newInstance()->name : null;
    }
}
