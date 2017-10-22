<?php

namespace Eigan\Mediasort;

use InvalidArgumentException;
use RuntimeException;
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

    /**
     * @var Callable[][]
     */
    private $subscribers;

    public function __construct(FilenameFormatter $formatter, string $rootPath = '')
    {
        parent::__construct();

        $this->formatter = $formatter;
        $this->rootPath = $rootPath;
        $this->subscribers = [];
    }

    /**
     * Subscribe to the actions emitted
     *
     * @param $key
     * @param callable $callback
     */
    public function subscribe($key, callable $callback)
    {
        if (isset($this->subscribers[$key]) === false) {
            $this->subscribers[$key] = [];
        }

        $this->subscribers[$key][] = $callback;
    }

    /**
     * Setup the command by adding arguments and options
     */
    protected function configure()
    {
        $this->setName('move');

        $this->addArgument('source', InputArgument::REQUIRED);
        $this->addArgument('destination', InputArgument::OPTIONAL);

        $this->addOption('format', '', InputOption::VALUE_OPTIONAL, 'The format', ':year/:month/:date :time');
        $this->addOption('only', '', InputOption::VALUE_OPTIONAL, 'Limit by extensions');
        $this->addOption('link', '', InputOption::VALUE_NONE, 'Use hardlink instead of moving');
        $this->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Go recursive');
        $this->addOption('ignore', '', InputOption::VALUE_OPTIONAL, 'Ignore files with extension');
        $this->addOption('only-type', '', InputOption::VALUE_REQUIRED, 'Only files with specific type', 'audio,image,video');
        $this->addOption('dry-run', '', InputOption::VALUE_NONE, 'Do not move the files');
    }

    /**
     * This method does everything
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $this->addStandardSubcribers($input, $output);

        $this->publish('start', [$input]);

        $format = $input->getOption('format');
        $shouldLink = $input->getOption('link');
        $recursive = $input->getOption('recursive');
        $dryRyn = $input->getOption('dry-run');

        if (empty($type = $input->getOption('only-type'))) {
            $output->writeln('<fg=white;bg=red>Missing value for --only-type</>');
            return;
        }

        try {
            list($source, $destination) = $this->resolvePaths($input);

            $this->formatter->setFormatter(':original', function ($path) use ($source) {
                $extension = pathinfo($path, PATHINFO_EXTENSION);

                if ($extension) {
                    $path = $this->replaceLastOccurrence('.'.$extension, '', $path);
                }

                return str_replace($source, '', $path);
            });
        } catch (InvalidArgumentException $e) {
            $output->write($e->getMessage());
            return;
        }

        $this->publish('paths.resolved', [
            'source' => $source,
            'destination' => $destination
        ]);

        foreach ($this->iterate($source, $recursive) as $fileSourcePath) {
            if ($this->shouldSkip($fileSourcePath, $input)) {
                continue;
            }

            $this->publish('iterate.start', [$fileSourcePath]);

            try {
                $fileDestinationPath = $this->formatDestinationPath($destination, $fileSourcePath, $format);
            } catch (RuntimeException $e) {
                $output->writeln('<fg=white;bg=red>'.$e->getMessage().'</>');
                continue;
            }

            if (file_exists($fileDestinationPath)) {
                if ($this->isDuplicate($fileSourcePath, $fileDestinationPath)) {
                    $this->publish('iterate.destinationDuplicate', [$fileSourcePath, $fileDestinationPath]);

                    continue;
                }

                $fileDestinationPath = $this->incrementPath($fileDestinationPath);
            }

            if (is_readable($fileSourcePath) === false) {
                $this->publish('iterate.sourceDisappeared', [$fileSourcePath]);
                continue;
            }

            if ($shouldLink) {
                $output->writeln("   $fileSourcePath");
            } else {
                $output->writeln(" <fg=red>- $fileSourcePath</> (move)");
            }

            $output->writeln(" <fg=green>+ $fileDestinationPath</>");

            if ($shouldLink) {
                if ($symfonyStyle->confirm('Create hardlink?') && !$dryRyn) {
                    $this->publish('iterate.link', [$fileSourcePath, $fileDestinationPath]);

                    $destinationIsOk = $this->mkdir($fileDestinationPath);

                    if ($destinationIsOk) {
                        link($fileSourcePath, $fileDestinationPath);
                    }
                }
            } else {
                if ($symfonyStyle->confirm('Move file?') && !$dryRyn) {
                    $this->publish('iterate.move', [$fileSourcePath, $fileDestinationPath]);

                    $destinationIsOk = $this->mkdir($fileDestinationPath);

                    if ($destinationIsOk) {
                        rename($fileSourcePath, $fileDestinationPath);
                    }
                }
            }

            $this->publish('iterate.end', [$fileSourcePath, $fileDestinationPath]);
        }

        $output->writeln(memory_get_usage(true));
        $output->writeln(memory_get_peak_usage(true));
    }

    private function addStandardSubcribers(InputInterface $input, OutputInterface $output)
    {
        if ($output->isVerbose()) {
            $this->addVerboseSubscriber($output);
        }
    }

    private function addVerboseSubscriber(OutputInterface $output)
    {
        $verboseSubscriber = new Subscribers\VerboseSubscriber($output);

        foreach ($verboseSubscriber->subscribe() as $key => $callback) {
            $this->subscribe($key, $callback);
        }
    }

    private function publish($key, $parts)
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
     * @return array [string $source, string $destination]
     *
     * @throws InvalidArgumentException
     */
    private function resolvePaths(InputInterface $input): array
    {
        $destination = $input->getArgument('destination') ?: $input->getArgument('source');

        $source = $this->realpath($input->getArgument('source'));
        $destination = $this->realpath($destination);

        $sourceComponents = parse_url($source);
        $destinationComponents = parse_url($destination);

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
     * @param $fileSourcePath
     * @param InputInterface $input
     *
     * @return bool
     */
    private function shouldSkip($fileSourcePath, InputInterface $input): bool
    {
        $ignore = $input->getOption('ignore');
        $only = $input->getOption('only');
        $type = $input->getOption('only-type');

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

        if (is_readable($fileSourcePath) === false) {
            return true;
        }

        $types = explode(',', $type);

        $extensions = $this->getTypesExtensions($types);

        $extension = strtolower(pathinfo($fileSourcePath, PATHINFO_EXTENSION));

        if (in_array($extension, $extensions, true)) {
            return false;
        }

        foreach ($types as $allowedType) {
            if (strpos(mime_content_type($fileSourcePath), $allowedType) !== false) {
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
            mkdir(dirname($fileDestinationPath), 0777, true);
        }

        if (is_writable(dirname($fileDestinationPath)) === false) {
            $this->publish('iterate.destinationNotWritable', [$fileDestinationPath]);

            return false;
        }

        return true;
    }

    /**
     * Litle utility to get the allowed extenstions given the $types sent via --only-type
     *
     * @param array $types
     *
     * @return array
     */
    private function getTypesExtensions(array $types): array
    {
        $allowed = [];

        foreach ($types as $type) {
            switch ($type) {
                case 'image':
                    $allowed = array_merge(
                        [
                            'bmp', 'cgm', 'g3', 'gif', 'ief', 'jpeg', 'jpg', 'jpe', 'ktx', 'png', 'btif', 'sgi',
                            'svg', 'svgz', 'tiff', 'tif', 'psd', 'uvi', 'uvvi', 'uvg', 'uvvg', 'sub', 'djvu',
                            'djv', 'dwg', 'dxf', 'fbs', 'fpx', 'fst', 'mmr', 'rlc', 'mdi', 'wdp', 'npx', 'wbmp',
                            'xif', 'webp', '3ds', 'ras', 'cmx', 'fh', 'fhc', 'fh4', 'fh5', 'fh7', 'ico', 'sid',
                            'pcx', 'pic', 'pct', 'pnm', 'pbm', 'pgm', 'ppm', 'rgb', 'tga', 'xbm', 'xpm', 'xwd'
                        ],
                        $allowed
                    );
                    break;
                case 'video':
                    $allowed = array_merge(
                        [
                            '3gp', '3g2', 'h261', 'h263', 'h264', 'jpgv', 'jpm', 'jpgm', 'mj2', 'mjp2', 'mp4',
                            'mp4v', 'mpg4', 'mpeg', 'mpg', 'mpe', 'm1v', 'm2v', 'ogv', 'qt', 'mov', 'uvh', 'uvvh',
                            'uvm', 'uvvm', 'uvp', 'uvvp', 'uvs', 'uvvs', 'uvv', 'uvvv', 'dvb', 'fvt', 'mxu', 'm4u',
                            'pyv', 'uvu', 'uvvu', 'viv', 'webm', 'f4v', 'fli', 'flv', 'm4v', 'mkv', 'mk3d', 'mks',
                            'mng', 'asf', 'asx', 'vob', 'wm', 'wmv', 'wmx', 'wvx', 'avi', 'movie', 'smv'
                        ],
                        $allowed
                    );
                    break;
                case 'audio':
                    $allowed = array_merge(
                        [
                            'adp', 'au', 'snd', 'mid', 'midi', 'kar', 'rmi', 'mp4a', 'mpga', 'mp2', 'mp2a', 'mp3',
                             'm2a', 'm3a', 'oga', 'ogg', 'spx', 's3m', 'sil', 'uva', 'uvva', 'eol', 'dra', 'dts',
                             'dtshd', 'lvp', 'pya', 'ecelp4800', 'ecelp7470', 'ecelp9600', 'rip', 'weba', 'aac',
                             'aif', 'aiff', 'aifc', 'caf', 'flac', 'mka', 'm3u', 'wax', 'wma', 'ram', 'ra', 'rmp',
                             'wav', 'xm'
                        ],
                        $allowed
                    );
                    break;
            }
        }

        return $allowed;
    }

    /**
     * Creates the destination path by using the format specified
     *
     * @param string $destination
     * @param string $fileSourcePath
     * @param string $format
     *
     * @return string
     */
    private function formatDestinationPath(string $destination, string $fileSourcePath, string $format): string
    {
        $mewPath = $this->formatter->format($format . ':ext', $fileSourcePath);

        if ($mewPath[0] !== '/') {
            return $destination . '/'  . $mewPath;
        }

        return $destination . $mewPath;
    }

    /**
     * Given a path, increment until the file doesnt exist anymore
     *
     * @param $fileDestinationPath
     *
     * @return string
     */
    private function incrementPath($fileDestinationPath): string
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

        $fileDestinationPath = $this->replaceLastOccurrence('.'.$extension, " (1).$extension", $fileDestinationPath);

        do {
            $fileDestinationPath = $increment($fileDestinationPath, ++$index);
        } while (file_exists($fileDestinationPath));

        return $fileDestinationPath;
    }

    /**
     * Check if the given path is a duplicate
     *
     * @param string $fileSourcePath
     * @param string $fileDestinationPath
     *
     * @return bool
     */
    private function isDuplicate(string $fileSourcePath, string $fileDestinationPath): bool
    {
        if (filesize($fileSourcePath) === filesize($fileDestinationPath)) {
            return hash_file('md5', $fileSourcePath) === hash_file('md5', $fileDestinationPath);
        }

        return false;
    }

    /**
     * Loop over the given $root directory and yield each path
     *
     * @param string $root
     * @param bool   $recursive
     *
     * @return \Iterator
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

            $paths[date('U', filemtime($pathname)) . $pathname] = $pathname;

            if ($file->isDir() && !$file->isDot()) {
                $dirs[] = $pathname;
            }
        }

        // Key is the file modified timestamp
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

    /**
     * Takes a path, and create absolute path of it
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
     * Replace last occurens of $search
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
}
