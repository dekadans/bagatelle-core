<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Console;

use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use tthe\Bagatelle\Config\AttributeClassFinder;

class ContainerCommandDefinitionLoader extends AttributeClassFinder implements CommandDefinitionLoaderInterface
{
    private string $directory;
    private array $options = [];
    private ConfigCacheFactoryInterface $cacheFactory;

    public function __construct(
        FileLocatorInterface $locator,
        string $directory,
        array $options,
        ?ConfigCacheFactoryInterface $cacheFactory = null
    ) {
        parent::__construct($locator, AsCommand::class);
        $this->directory = $directory;
        $this->setOptions($options);
        $this->cacheFactory = $cacheFactory ?? new ConfigCacheFactory($this->options['debug']);
    }

    /**
     * Loads available console commands either from cache or by dynamically scanning a given directory.
     * @return array
     */
    public function load(): array
    {
        if ($this->options['cache']) {
            $cache = $this->cacheFactory->cache(
                rtrim($this->options['cache'], '/') . '/command_map.php',
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
        }

        return $this->getCommandMap();
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
            $this->findInDirectory($this->directory),
            $this->findInList($this->options['extra'])
        );

        $commands = [];
        foreach ($classes as $class => $attributes) {
            $command = $attributes[0]->newInstance()->name;
            $commands[$command] = $class;
        }

        return $commands;
    }
}
