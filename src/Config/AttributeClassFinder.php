<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Config;

use Symfony\Component\Config\FileLocatorInterface;

/**
 * Base class for discovery components.
 */
abstract class AttributeClassFinder
{
    public function __construct(
        protected FileLocatorInterface $locator,
        protected string $attribute
    ) {}

    /**
     * @param string $location
     * @return array<class-string, \ReflectionAttribute[]>
     */
    protected function findInDirectory(string $location): array
    {
        return $this->findInList($this->findClassesInDirectory($location));
    }

    /**
     * @param array $classes
     * @return array<class-string, \ReflectionAttribute[]>
     */
    protected function findInList(array $classes): array
    {
        $result = [];

        foreach ($classes as $class) {
            $classAttributes = $this->findAttribute($class);
            if ($classAttributes) {
                $result[$class] = $classAttributes;
            }
        }

        return $result;
    }

    /**
     * Recursively finds PHP classes in a directory.
     * @param string $location
     * @return array
     */
    private function findClassesInDirectory(string $location): array
    {
        $directory = $this->locator->locate($location);
        /** @var iterable<\SplFileInfo> $files */
        $files = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                static fn(\SplFileInfo $f) => $f->getExtension() === 'php' || $f->isDir()
            )
        );

        $classes = array_filter(
            array_map($this->parseFile(...), iterator_to_array($files))
        );
        return array_values($classes);
    }

    /**
     * Returns (the first) qualified name of a defined class in the given file.
     * @param string $path
     * @return string|null
     */
    private function parseFile(string $path): ?string
    {
        $qualifiedName = '';
        $section = null;
        $phpTokens = token_get_all(file_get_contents($path));

        for ($i = 0; $i < count($phpTokens); $i++) {
            if (!is_array($phpTokens[$i])) {
                continue;
            }

            [$token, $value] = $phpTokens[$i];

            if ($section === T_NAMESPACE && in_array($token, [T_NAME_QUALIFIED, T_NS_SEPARATOR, T_STRING])) {
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
     * @param class-string $className
     * @return \ReflectionAttribute[]
     */
    private function findAttribute(string $className): array
    {
        return new \ReflectionClass($className)->getAttributes(
            $this->attribute,
            \ReflectionAttribute::IS_INSTANCEOF
        );
    }
}
