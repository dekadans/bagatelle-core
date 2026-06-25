<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Exception\FileLocatorFileNotFoundException;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('bagatelle:clear', 'Clear cached artifacts.')]
class ClearCacheCommand extends Command
{
    public function __construct(
        private LoggerInterface $logger,
        private FileLocatorInterface $locator
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Run the command without actually deleting anything.', shortcut: 't')]
        bool $test = false
    ): int {
        $vars = ['CACHE_CONTAINER', 'CACHE_CONTROLLERS', 'CACHE_COMMANDS', 'CACHE_TEMPLATES'];

        if ($test && !$io->isVerbose()) {
            $io->info('Test mode: increase verbosity (-v or -vv) to print details about what would be deleted.');
        }

        foreach ($vars as $var) {
            $shortPath = $_ENV[$var] ?? null;
            if (!$shortPath) {
                $this->logger->info("Variable $var is unset, skipping...");
                continue;
            }

            try {
                $directory = $this->locator->locate($shortPath);
            } catch (FileLocatorFileNotFoundException $e) {
                $this->logger->info("$shortPath does not exist, skipping...");
                continue;
            }

            $this->logger->info("Deleting contents in $shortPath");
            $this->deleteRecursive($directory, $test);
        }

        if (!$test) {
            $io->success('Removed all cached resources!');
        } else {
            $io->note('Nothing was deleted during this test run.');
        }

        return Command::SUCCESS;
    }

    private function deleteRecursive(string $path, bool $test): void
    {
        /** @var iterable<\SplFileInfo> $files */
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $this->deleteFile($file, $test);
        }
    }

    private function deleteFile(\SplFileInfo $file, bool $test): void
    {
        $this->logger->notice("Deleting {$file->getPathname()}");

        if ($test) {
            return;
        }

        $operation = $file->isDir() ? rmdir(...) : unlink(...);

        if (!$operation($file->getPathname())) {
            $this->logger->error("Could not delete: {$file->getPathname()}");
        }
    }
}