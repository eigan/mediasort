<?php

namespace Eigan\Mediasort;

use Eigan\Mediasort\Exception\IncrementedPathIsDuplicate;
use InvalidArgumentException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use RuntimeException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function file_exists;
use function filemtime;
use function filter_var;
use function is_array;
use function is_string;
use function pathinfo;
use function preg_replace;
use function random_int;
use function str_replace;
use const FILTER_VALIDATE_BOOLEAN;
use const PATHINFO_EXTENSION;

class Command extends SymfonyCommand
{
    /**
     * @var FilenameFormatter
     */
    private $formatter;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var string
     */
    private $rootPath;

    /**
     * @var Callable[][]
     */
    private $subscribers;

    public function __construct(FilenameFormatter $formatter, Logger $logger, string $rootPath = '')
    {
        parent::__construct();

        $this->formatter = $formatter;
        $this->logger = $logger;
        $this->rootPath = $rootPath;
        $this->subscribers = [];
    }

    /**
     * @return Application
     */
    public function getApplication()
    {
        $app = parent::getApplication();

        if (!($app instanceof Application)) {
            throw new InvalidArgumentException("Failed to create the application");
        }

        return $app;
    }

    /**
     * Subscribe to the events
     * Just used by the verbose subscriber
     *
     * @param string   $key
     * @param callable $callback
     *
     * @return void
     */
    public function subscribe(string $key, callable $callback)
    {
        if (isset($this->subscribers[$key]) === false) {
            $this->subscribers[$key] = [];
        }

        $this->subscribers[$key][] = $callback;
    }

    /**
     * Setup the command by adding arguments and options
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('sort');

        $this->addArgument('source', InputArgument::REQUIRED);
        $this->addArgument('destination', InputArgument::OPTIONAL);

        $this->addOption('format', '', InputOption::VALUE_OPTIONAL, 'The format', ':year/:month/:date :time');
        $this->addOption('only', '', InputOption::VALUE_OPTIONAL, 'Comma separated list of extensions');
        $this->addOption('link', '', InputOption::VALUE_NONE, 'Use hardlink instead of move');
        $this->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Scan for files in subdirectories');
        $this->addOption('ignore', '', InputOption::VALUE_OPTIONAL, 'Ignore files with extension');
        $this->addOption('only-type', '', InputOption::VALUE_REQUIRED, 'Only files with specific type', 'audio,image,video');
        $this->addOption('dry-run', '', InputOption::VALUE_NONE, 'Do not move or link files');
        $this->addOption('log-path', '', InputOption::VALUE_OPTIONAL, 'Path to where write logfile');
    }

    /**
     * This method does everything
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        if ($output->isVerbose()) {
            $this->addVerboseSubscriber($output);
        }

        $this->publish('start', [$input]);

        $format = $input->getOption('format');

        if (!is_string($format)) {
            $output->writeln("Failed to read the format");
            return 1;
        }

        $shouldLink = filter_var($input->getOption('link'), FILTER_VALIDATE_BOOLEAN);
        $recursive = filter_var($input->getOption('recursive'), FILTER_VALIDATE_BOOLEAN);
        $dryRyn = filter_var($input->getOption('dry-run'), FILTER_VALIDATE_BOOLEAN);

        $logPath = $input->getOption('log-path');

        if (is_string($logPath)) {
            try {
                $this->setupLogger($logPath);
            } catch (InvalidArgumentException $e) {
                $output->writeln($e->getMessage());
                return 1;
            }
        }

        if (empty($type = $input->getOption('only-type'))) {
            $output->writeln('<fg=white;bg=red>Missing value for --only-type</>');
            return 1;
        }

        try {
            list($source, $destination) = $this->resolvePaths($input);

            $this->formatter->setFormatter(':original', function (File $file) use ($source) {
                $extension = $file->getExtension();

                $path = $file->getPath();
                if ($extension) {
                    $path = $this->replaceLastOccurrence('.'.$extension, '', $path);
                }

                return str_replace($source, '', $path);
            });
        } catch (InvalidArgumentException $e) {
            $output->write($e->getMessage());
            return 1;
        }

        $this->publish('paths.resolved', [
            'source' => $source,
            'destination' => $destination
        ]);

        if ($this->getApplication()->shouldUseExif($input) === false) {
            $output->writeln('<fg=white;bg=red>!</>');
            $output->writeln('<fg=white;bg=red>Exif not enabled. Dates might be incorrect!</>');
            $output->writeln('<fg=white;bg=red>!</>');

            $this->logger->warning('Exif not enabled. Dates might be incorrect');

            $this->formatter->setUseExif(false);
        }

        foreach ($this->iterate($source, $recursive) as $sourceFile) {
            if ($this->shouldSkip($sourceFile, $input)) {
                continue;
            }

            $this->publish('iterate.start', [$sourceFile]);

            if ($output->isVeryVerbose()) {
                $output->writeln('-----');
                $output->writeln($sourceFile->getPath());

                $this->outputFormatResults($output, $sourceFile);
            }

            try {
                $fileDestinationPath = $this->formatDestinationPath($destination, $sourceFile, $format);
            } catch (RuntimeException $e) {
                $output->writeln("<fg=yellow>Skipped: Format failed {$sourceFile->getPath()}</>");

                if ($output->isVerbose()) {
                    $output->writeln(' <fg=red>'.$e->getMessage().'</>');
                }

                continue;
            }

            if (file_exists($fileDestinationPath)) {
                if ($this->isDuplicate($sourceFile, $fileDestinationPath)) {
                    $output->writeln("<fg=yellow>Skipped: Duplicate {$sourceFile->getPath()} -> $fileDestinationPath</>");
                    continue;
                }

                try {
                    $incrementedPath = $this->incrementPath($sourceFile, $fileDestinationPath);
                } catch (IncrementedPathIsDuplicate $e) {
                    $output->writeln("<fg=yellow>Skipped: Duplicate {$sourceFile->getPath()} -> {$e->getIncrementedPath()}</>");
                    continue;
                }

                $fileDestinationPath = $incrementedPath;
            }

            if ($sourceFile->isReadable() === false) {
                continue;
            }

            if ($shouldLink) {
                $output->writeln("  {$sourceFile->getPath()}");
            } else {
                $output->writeln("<fg=red>- {$sourceFile->getPath()}</> (move)");
            }

            $output->writeln("<fg=green>+ $fileDestinationPath</>");

            $success = false;
            $confirmed = false;
            $destinationIsOk = false;

            if ($shouldLink) {
                $confirmed = $symfonyStyle->confirm('Create hardlink?');

                if ($confirmed && !$dryRyn) {
                    $destinationIsOk = $this->mkdir($fileDestinationPath);

                    if ($destinationIsOk) {
                        $success = link($sourceFile->getPath(), $fileDestinationPath);
                    } else {
                        $output->writeln("<fg=yellow>Skipped: Not writable ($fileDestinationPath)</>");
                    }
                }
            } else {
                $confirmed = $symfonyStyle->confirm('Move file?');

                if ($confirmed && !$dryRyn) {
                    $destinationIsOk = $this->mkdir($fileDestinationPath);

                    if ($destinationIsOk) {
                        $success = rename($sourceFile->getPath(), $fileDestinationPath);
                    } else {
                        $output->writeln("<fg=yellow>Skipped: Not writable ($fileDestinationPath)</>");
                    }
                }
            }

            if ($success) {
                $this->logger->info(($shouldLink ? 'link' : 'move').' "'.$sourceFile->getPath().'" "'.$fileDestinationPath.'"');
            } elseif ($confirmed && !$dryRyn) {
                $output->writeln('<fg=yellow>Operation failed</>');
                $this->logger->info('failed "'.$sourceFile->getPath().'" "'.$fileDestinationPath.'" ' .($destinationIsOk ? 'Unknown' : 'Destination not OK'));
            }
        }

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param File $sourceFile
     *
     * @return void
     */
    private function outputFormatResults(OutputInterface $output, File $sourceFile)
    {
        $table = new Table($output);

        $table->setHeaders(['Format', 'Result']);

        foreach ($this->formatter->getFormats() as $format) {
            try {
                $result = $this->formatter->format($format, $sourceFile);
            } catch (\Exception $e) {
                continue;
            }

            $table->addRow([$format, $result]);
        }

        $table->render();
    }

    /**
     * @param string $logPath
     * @throws \Exception
     *
     * @return void
     */
    private function setupLogger(string $logPath)
    {
        if (file_exists($logPath) === false) {
            throw new \InvalidArgumentException("Log path does not exist: [$logPath]");
        }

        if (is_writable($logPath) === false) {
            throw new \InvalidArgumentException("Log path is not writable: [$logPath]");
        }

        $logPath .= '/mediasort.log';

        if(file_exists($logPath)) {
            $logPath = $this->replaceLastOccurrence('.log', " (1).log", $logPath);

            $i = 1;
            while (file_exists($logPath)) {
                $logPath = $this->incrementFilename($logPath, $i++);

            }
        }



        $streamHandler = new StreamHandler($logPath);
        $streamHandler->setFormatter(new LineFormatter("%message%\n"));

        $this->logger->setHandlers([$streamHandler]);
    }

    /**
     * Just wanted to move some of the heavy verbose stuff away from this file
     *
     * @param OutputInterface $output
     *
     * @return void
     */
    private function addVerboseSubscriber(OutputInterface $output)
    {
        $verboseSubscriber = new Subscribers\VerboseSubscriber($output, $this->formatter);

        foreach ($verboseSubscriber->subscribe() as $key => $callback) {
            $this->subscribe($key, $callback);
        }
    }

    /**
     * Publish and event
     * Just used by the verbose subscriber
     *
     * @param string        $key
     * @param array<mixed>  $parts payload
     *
     * @return void
     */
    private function publish(string $key, array $parts)
    {
        if (isset($this->subscribers[$key])) {
            foreach ($this->subscribers[$key] as $subscriber) {
                call_user_func_array($subscriber, $parts);
            }
        }
    }

    /**
     * Resolve `source` and `destination` arguments
     *
     * @param InputInterface $input
     *
     * @return array{0: string, 1: string} [$source, $destination]
     *
     * @throws InvalidArgumentException
     */
    private function resolvePaths(InputInterface $input): array
    {
        $argSource = $input->getArgument('source');
        $argDestination = $input->getArgument('destination') ?: $input->getArgument('source');

        if (!is_string($argSource) || !is_string($argDestination)) {
            throw new InvalidArgumentException("Got invalid paths!");
        }

        $source = $this->realpath($argSource);
        $destination = $this->realpath($argDestination);

        $sourceComponents = parse_url($source);
        $destinationComponents = parse_url($destination);

        if (!is_array($sourceComponents) || !is_array($destinationComponents)) {
            throw new InvalidArgumentException("Failed to info about paths!");
        }

        if (
            (isset($sourceComponents['scheme']) && !isset($destinationComponents['scheme'])) ||
            (isset($destinationComponents['scheme']) && !isset($sourceComponents['scheme'])) ||
            (
                isset($sourceComponents['scheme'], $destinationComponents['scheme']) &&
                $sourceComponents['scheme'] !== $destinationComponents['scheme']
            )
        ) {
            throw new InvalidArgumentException("Mediasort doesn't support operations across wrapper types");
        }

        $source = rtrim($source, '/');
        $destination = rtrim($destination, '/');

        if (is_dir($source) === false) {
            throw new InvalidArgumentException('Source is not a directory');
        }

        if (is_dir($destination) === false) {
            throw new InvalidArgumentException('Destination is not a directory');
        }

        if (is_readable($source) === false) {
            throw new InvalidArgumentException('Source is not readable');
        }

        if (is_writable($destination) === false) {
            throw new InvalidArgumentException('Destination is not writable');
        }

        return [$source, $destination];
    }

    /**
     * Determine if the given path can be skipped
     *
     * @param File           $file
     * @param InputInterface $input
     *
     * @return bool
     */
    private function shouldSkip(File $file, InputInterface $input): bool
    {
        $ignore = $input->getOption('ignore');
        $only = $input->getOption('only');
        $type = $input->getOption('only-type');

        if (is_string($ignore)) {
            $ignoreInput = explode(',', $ignore);
            $extensions = array_map(function ($ext) {
                return trim($ext);
            }, $ignoreInput);

            if (in_array($file->getExtension(), $extensions, true)) {
                return true;
            }
        }

        if (is_string($only)) {
            $input = explode(',', $only);
            $extensions = array_map(function ($ext) {
                return trim($ext);
            }, $input);

            if (!in_array($file->getExtension(), $extensions, true)) {
                return true;
            }
        }

        if ($file->isReadable() === false) {
            return true;
        }

        if ($file->getSize() === 0) {
            return true;
        }

        $types = [];

        if (is_string($type)) {
            $types = explode(',', $type);
        }

        if (in_array($file->getType(), $types, true)) {
            return false;
        }

        foreach ($types as $allowedType) {
            if (strpos($file->getMimeType(), $allowedType) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Creates the full path to where we want to put the file
     *
     * @param  string $fileDestinationPath
     * @return bool
     */
    private function mkdir(string $fileDestinationPath): bool
    {
        if (file_exists(dirname($fileDestinationPath)) === false) {
            mkdir(dirname($fileDestinationPath), 0744, true);
        }

        if (is_writable(dirname($fileDestinationPath)) === false) {
            return false;
        }

        return true;
    }

    /**
     * @param string $destination
     * @param File   $sourceFile
     * @param string $format
     *
     * @return string
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    private function formatDestinationPath(string $destination, File $sourceFile, string $format): string
    {
        $newPath = $this->formatter->format($format . ':ext', $sourceFile);

        if ($newPath[0] !== '/') {
            return $destination . '/'  . $newPath;
        }

        return $destination . $newPath;
    }

    /**
     * Given a path, increment until we get a usable filename
     *
     * @param File   $sourceFile
     * @param string $fileDestinationPath
     *
     * @return string
     *
     * @throws IncrementedPathIsDuplicate
     */
    private function incrementPath(File $sourceFile, string $fileDestinationPath)
    {
        $index = 0;

        $extension = pathinfo($fileDestinationPath, PATHINFO_EXTENSION);
        $isNotDuplicate = false;

        if ($extension) {
            $fileDestinationPath = $this->replaceLastOccurrence('.'.$extension, " (1).$extension", $fileDestinationPath);
        } else {
            $fileDestinationPath .= ' (1)';
        }

        do {
            $fileDestinationPath = $this->incrementFilename($fileDestinationPath, ++$index);
        } while (file_exists($fileDestinationPath) && $isNotDuplicate = !$this->isDuplicate($sourceFile, $fileDestinationPath));

        if ($isNotDuplicate === false && file_exists($fileDestinationPath)) {
            throw new IncrementedPathIsDuplicate($sourceFile, $fileDestinationPath);
        }

        return $fileDestinationPath;
    }

    /**
     * Check if the given path is a duplicate
     *
     * @param File   $sourceFile
     * @param string $fileDestinationPath
     *
     * @return bool
     */
    private function isDuplicate(File $sourceFile, string $fileDestinationPath): bool
    {
        if ($sourceFile->getSize() === filesize($fileDestinationPath)) {
            $sourceInode = fileinode($sourceFile->getPath());
            $destinationInode = fileinode($fileDestinationPath);

            if ((!$sourceInode && !$destinationInode) || ($sourceInode !== $destinationInode)) {
                return hash_file('md5', $sourceFile->getPath()) === hash_file('md5', $fileDestinationPath);
            }

            return $sourceInode === $destinationInode;
        }

        return false;
    }

    /**
     * Loop over the given $root directory and yield each path
     *
     * @param string $root
     * @param bool   $recursive
     *
     * @return \Iterator<File>
     */
    private function iterate(string $root, bool $recursive = false): \Iterator
    {
        $paths = [];
        $dirs = [];

        if (is_readable($root) === false) {
            return;
        }

        foreach (new \DirectoryIterator($root) as $fileName => $file) {
            $pathname = $file->getPathname();

            if ($file->getFilename() === '.nomedia') {
                $dirs = $paths = [];
                break;
            }

            if (strpos($file->getPath(), '/@eaDir') !== false) {
                $dirs = $paths = [];
                break;
            }

            if ($file->isDir() === false) {
                $modifiedTime = filemtime($pathname);

                if ($modifiedTime === false) {
                    $modifiedTime = random_int(1, 1000);
                }

                $paths[date('U', $modifiedTime) . $pathname] = $pathname;
            }

            if ($file->isDir() && !$file->isDot()) {
                $dirs[] = $pathname;
            }
        }

        // Key is the file modified timestamp
        ksort($paths);

        foreach ($paths as $mtime => $path) {
            $file = new File($path);
            yield $file;
        }

        if ($recursive) {
            foreach ($dirs as $dir) {
                yield from $this->iterate($dir, $recursive);
            }
        }
    }

    /**
     * Takes a path, and create path that is readable from current position (file_exists())
     *
     * @param string $path
     *
     * @return string
     */
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

        if (!file_exists($real)) {
            throw new InvalidArgumentException("Path [$path] does not exist");
        }

        return $real;
    }

    /**
     * Replace last occurrence of $search
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     *
     * @return string
     */
    private function replaceLastOccurrence(string $search, string $replace, string $subject): string
    {
        $pos = strrpos($subject, $search);

        if ($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }

    /**
     * @param string $path
     * @param int $index
     * @return string
     */
    private function incrementFilename(string $path, int $index)
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $regex = "\((\d*)\)(?!.*\((\d*)\))";
        $replace = '('.$index.')';

        if ($extension) {
            $regex .= ".*$extension";
            $replace .= ".$extension";
        }

        return preg_replace('/'.$regex.'/', $replace, $path, 1);
    }
}
