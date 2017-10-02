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

    public function __construct(string $rootPath = null)
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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $format = $input->getOption('format');
        $shouldLink = $input->getOption('link');
        $recursive = $input->getOption('recursive');

        $io = new SymfonyStyle($input, $output);

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

        $only = $input->getOption('only');

        foreach ($this->iterate($source, $recursive) as $fileSourcePath => $file) {
            $fileSourcePath = $file->getPathname();

            if ($this->shouldSkip($fileSourcePath, $only)) {
                continue;
            }

            if ($output->isVerbose()) {
                $io->note('Source: ' . $fileSourcePath);
            }

            try {
                $fileDestinationPath = $this->makeFileDestinationPath($destination, $source, $fileSourcePath, $format);
            } catch (\RuntimeException $e) {
                $io->error($e->getMessage());
            }

            if ($output->isVerbose()) {
                $io->text('Destination: ' . $fileDestinationPath);
            }

            if (file_exists($fileDestinationPath)) {
                if ($this->isDuplicate($fileSourcePath, $fileDestinationPath)) {
                    if ($output->isVerbose()) {
                        $io->text('Skipped: Duplicate');
                    }
                        
                    continue;
                }

                $fileDestinationPath = $this->incrementPath($fileDestinationPath);
            }

            if (file_exists(dirname($fileDestinationPath)) === false) {
                mkdir(dirname($fileDestinationPath), 0777, true);
            }

            if ($input->isInteractive()) {
                $io->text("Source: $fileSourcePath");
                $io->text("Destination: $fileDestinationPath");
            }

            if ($shouldLink) {
                if ($io->confirm('Move?')) {
                    echo 'MOVING';
                    link($fileSourcePath, $fileDestinationPath);
                } else {
                    echo 'NOPE';
                }
            } else {
                if ($io->confirm('Create hardlink?')) {
                    echo 'REAN';
                    rename($fileSourcePath, $fileDestinationPath);
                } else {
                    echo 'NOPE';
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

    private function shouldSkip($fileSourcePath, ?string $only)
    {
        if (is_dir($fileSourcePath)) {
            return true;
        }

        if ($only) {
            $input = explode(',', $only);
            $extensions = array_map(function ($ext) {
                return trim($ext);
            }, $input);

            return !in_array(pathinfo($fileSourcePath, PATHINFO_EXTENSION), $extensions, true);
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

    private function iterate(string $path, bool $recursive = false): \Iterator
    {
        if ($recursive) {
            return new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        }

        return new \DirectoryIterator($path);
    }

    private function realpath(string $path): string
    {
        $real = $path;

        if (function_exists('posix_getuid') && strpos($path, '~') !== false) {
            $info = posix_getpwuid(posix_getuid());
            $real = str_replace('~', $info['dir'], $path);
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
