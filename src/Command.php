<?php

namespace Eig\PrettyTree;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new OutputStyle($input, $output);

        $this->addStandardSubcribers($input, $output, $io);

        $this->publish('start', [$input]);

        $format = $input->getOption('format');
        $shouldLink = $input->getOption('link');
        $recursive = $input->getOption('recursive');
        $dryRyn = $input->getOption('dry-run');

        if (empty($type = $input->getOption('only-type'))) {
            $io->error('Missing value for --only-type');
            return;
        }

        try {
            list($source, $destination) = $this->resolvePaths($input);

            $this->formatter->setFormatter(':original', function ($path) use ($source) {
                $extension = pathinfo($path, PATHINFO_EXTENSION);

                if ($extension) {
                    $path = $this->str_lreplace('.'.$extension, '', $path);
                }

                return str_replace($source, '', $path);
            });
        } catch (\InvalidArgumentException $e) {
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
                $fileDestinationPath = $this->makeFileDestinationPath($destination, $source, $fileSourcePath, $format);
            } catch (\RuntimeException $e) {
                $io->error($e->getMessage());
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
                $io->linkPath($fileSourcePath);
            } else {
                $io->movePath($fileSourcePath);
            }

            $io->destinationPath($fileDestinationPath);

            if ($shouldLink) {
                if ($io->confirm('Create hardlink?') && !$dryRyn) {
                    $this->publish('iterate.link', [$fileSourcePath, $fileDestinationPath]);

                    $destinationIsOk = $this->mkdir($fileDestinationPath);

                    if($destinationIsOk) {
                        link($fileSourcePath, $fileDestinationPath);
                    }
                }
            } else {
                if ($io->confirm('Move file?') && !$dryRyn) {
                    $this->publish('iterate.move', [$fileSourcePath, $fileDestinationPath]);

                    $destinationIsOk = $this->mkdir($fileDestinationPath);

                    if($destinationIsOk) {
                        rename($fileSourcePath, $fileDestinationPath);
                    }
                }
            }

            $this->publish('iterate.end', [$fileSourcePath, $fileDestinationPath]);
        }
    }

    private function addStandardSubcribers(InputInterface $input, OutputInterface $output, OutputStyle $io)
    {
        if ($output->isVerbose()) {
            $this->addVerboseSubscriber($io);
        }
    }

    private function addVerboseSubscriber(OutputStyle $io)
    {
        $verboseSubscriber = new Subscribers\VerboseSubscriber($io);

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

    protected function resolvePaths(InputInterface $input): array
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
            throw new \InvalidArgumentException("PrettyTree doesn't support operations across wrapper types");
        }

        if (is_readable($source) === false) {
            throw new \InvalidArgumentException('Source is not readable');
        }

        if (is_writable($destination) === false) {
            throw new \InvalidArgumentException('Destination is not writable');
        }

        return [$source, $destination];
    }

    private function shouldSkip($fileSourcePath, InputInterface $input)
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

    private function getTypesExtensions(array $types)
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

    private function makeFileDestinationPath(string $destination, string $source, string $fileSourcePath, string $format)
    {
        $mewPath = $this->formatter->format($format . ':ext', $fileSourcePath);

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

        $fileDestinationPath = $this->str_lreplace('.'.$extension, " (1).$extension", $fileDestinationPath);

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

    /**
     * Replace last occurens of $search
     *
     * @param $search
     * @param $replace
     * @param $subject
     * @return mixed
     */
    private function str_lreplace($search, $replace, $subject)
    {
        $pos = strrpos($subject, $search);

        if ($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }
}
