<?php

namespace Eig\PrettyTree;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Command extends SymfonyCommand
{
    /**
     * @var FilenameFormatter
     */
    private $formatter;

    /**
     * @var string
     */
    private $rootPath;

    public function __construct(string $rootPath = '')
    {
        parent::__construct(null);

        $this->rootPath = $rootPath;
    }

    protected function configure()
    {
        $this->setName('move');

        $this->addArgument('source', InputArgument::REQUIRED);
        $this->addArgument('destination', InputArgument::REQUIRED);

        $this->addOption('format', '', InputOption::VALUE_OPTIONAL, 'The format', ':original');
        $this->addOption('only', '', InputOption::VALUE_OPTIONAL, 'Limit by extensions');
        $this->addOption('link', '', InputOption::VALUE_NONE, 'Use hardlink instead of moving');
        $this->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Go recursive');
        $this->addOption('ignore', '', InputOption::VALUE_OPTIONAL, 'Ignore files with extension');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $format = $input->getOption('format');
        $shouldLink = $input->getOption('link');
        $recursive = $input->getOption('recursive');
        $ignore = $input->getOption('ignore');

        $io = new SymfonyStyle($input, $output);

        if ($output->isVerbose()) {
            $io->writeln("Format: $format");
            $io->writeln("Use hardlink:\t$shouldLink");
            $io->writeln("Recursive: $recursive");
            $io->writeln("Ignore: $ignore");
        }

        try {
            list($source, $destination) = $this->resolvePaths($input);

            $this->formatter = new FilenameFormatter();

            $this->formatter->setFormatter(':original', function ($path) use ($source) {
                return str_replace($source, '', $path);
            });
        } catch (\InvalidArgumentException $e) {
            $output->write($e->getMessage());
            return;
        }

        if ($output->isVerbose()) {
            $io->section("Source: $source\nDestination: $destination");
        }

        $only = $input->getOption('only');

        foreach ($this->iterate($source, $recursive) as $fileSourcePath) {
            if ($this->shouldSkip($fileSourcePath, $ignore, $only)) {
                continue;
            }

            if ($output->isVerbose()) {
                $io->note('File: ' . $fileSourcePath);
            }

            if ($input->isInteractive() && !$output->isVerbose()) {
                $io->text("Source: $fileSourcePath");
            }

            try {
                $fileDestinationPath = $this->makeFileDestinationPath($destination, $source, $fileSourcePath, $format);
            } catch (\RuntimeException $e) {
                $io->error($e->getMessage());
                continue;
            }

            if ($output->isVerbose()) {
                $io->text('Destination: ' . $fileDestinationPath);
            }

            if (file_exists($fileDestinationPath)) {
                if ($output->isVerbose()) {
                    $io->text('Destination exists');
                }

                if ($this->isDuplicate($fileSourcePath, $fileDestinationPath)) {
                    if ($output->isVerbose()) {
                        $io->success('Skipped: Duplicate');
                    }
                        
                    continue;
                }

                $fileDestinationPath = $this->incrementPath($fileDestinationPath);
            }

            if (file_exists(dirname($fileDestinationPath)) === false) {
                mkdir(dirname($fileDestinationPath), 0777, true);
            }

            if ($input->isInteractive()) {
                $io->text("Destination: $fileDestinationPath");
            }

            if ($shouldLink) {
                if ($io->confirm('Create hardlink?')) {
                    link($fileSourcePath, $fileDestinationPath);
                }
            } else {
                if ($io->confirm('Move?')) {
                    rename($fileSourcePath, $fileDestinationPath);
                }
            }
        }
    }

    protected function resolvePaths(InputInterface $input): array
    {
        return [
          $this->realpath($input->getArgument('source')),
          $this->realpath($input->getArgument('destination'))
        ];
    }

    private function shouldSkip($fileSourcePath, $ignore = false, $only = false)
    {
        if (is_dir($fileSourcePath)) {
            return true;
        }

        if ($ignore) {
            $ignoreInput = explode(',', $ignore);
            $extensions = array_map(function ($ext) {
                return trim($ext);
            }, $ignoreInput);

            if (in_array(pathinfo($fileSourcePath, PATHINFO_EXTENSION), $extensions, true)) {
                return true;
            }
        }

        if ($only) {
            $input = explode(',', $only);
            $extensions = array_map(function ($ext) {
                return trim($ext);
            }, $input);

            if (!in_array(pathinfo($fileSourcePath, PATHINFO_EXTENSION), $extensions, true)) {
                return true;
            }
        }

        return false;
    }

    private function makeFileDestinationPath(string $destination, string $source, string $fileSourcePath, string $format)
    {
        $mewPath = $this->formatter->format($format, $fileSourcePath);

        if ($mewPath[0] !== '/') {
            return $destination . '/'  . $mewPath;
        }

        return $destination . $mewPath;
    }

    private function incrementPath($fileDestinationPath)
    {
        $index = 0;

        $increment = function ($path, $index) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);

            $regex = "\((\d*)\)(?!.*\((\d*)\))";
            $replace = '('.$index.')';

            if ($extension) {
                $regex .= ".*$extension";
                $replace .= ".$extension";
            }

            return preg_replace('/'.$regex.'/', $replace, $path, 1);
        };

        $extension = pathinfo($fileDestinationPath, PATHINFO_EXTENSION);

        if ($extension) {
            $fileDestinationPath = $this->str_lreplace('.'.$extension, " (1).$extension", $fileDestinationPath);
        } else {
            $fileDestinationPath .= ' (1)';
        }

        do {
            $fileDestinationPath = $increment($fileDestinationPath, ++$index);
        } while (file_exists($fileDestinationPath));

        return $fileDestinationPath;
    }

    private function isDuplicate(string $fileSourcePath, $fileDestinationPath)
    {
        return hash_file('md5', $fileSourcePath) === hash_file('md5', $fileDestinationPath);
    }

    private function iterate(string $root, bool $recursive = false): \Iterator
    {
        $paths = [];
        $dirs = [];

        foreach (new \DirectoryIterator($root) as $fileName => $file) {
            $pathname = $file->getPathname();

            $paths[date('U', filemtime($pathname)) . $pathname] = $pathname;

            if ($file->isDir() && !$file->isDot()) {
                $dirs[] = $pathname;
            }
        }

        ksort($paths);

        foreach ($paths as $path) {
            yield $path;
        }

        if ($recursive) {
            foreach ($dirs as $dir) {
                yield from $this->iterate($dir, $recursive);
            }
        }
    }

    private function realpath(string $path): string
    {
        $real = $path;

        if (function_exists('posix_getuid') && strpos($path, '~') !== false) {
            $info = posix_getpwuid(posix_getuid());
            $real = str_replace('~', $info['dir'], $path);
            $real = str_replace('///', '//', $real);
        }

        if (!file_exists($real)) {
            $real = $this->rootPath . '/' . $real;
        }

        // Didnt work with vfs
        //$path = realpath($path);

        if (!file_exists($real)) {
            throw new \InvalidArgumentException("Path [$path] does not exist");
        }

        return $real;
    }

    private function str_lreplace($search, $replace, $subject)
    {
        $pos = strrpos($subject, $search);

        if ($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }
}
