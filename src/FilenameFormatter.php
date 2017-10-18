<?php

namespace Eig\PrettyTree;

use InvalidArgumentException;
use RuntimeException;

class FilenameFormatter
{
    /**
     * @var Callable[]
     */
    private $formatters;

    /**
     * @var array
     */
    private $exifCache;

    public function __construct()
    {
        $this->setupFormatters();
    }

    /**
     * Sets a formatter, note that you can actually override another formatter here.
     *
     * @param $name
     *
     * @param callable $formatter
     */
    public function setFormatter($name, callable $formatter)
    {
        $this->formatters[$name] = $formatter;
    }

    /**
     * Formats the given path into the format. Note that path should exist
     *
     * @param string $format
     * @param string $path
     *
     * @return string
     *
     * @throws RuntimeException         When a formatter fails
     * @throws InvalidArgumentException When the formatter doesnt exist
     */
    public function format(string $format, string $path): string
    {
        $callbacks = [];

        preg_match_all('/\:([a-zA-Z_\x7f-\xff][a-zA-Z0-9_]*)/', $format, $matches);

        if (isset($matches[0]) === false || empty($matches[0]) || !is_array($matches[0])) {
            throw new InvalidArgumentException('No valid formats');
        }

        foreach ($matches[0] as $formatterPattern) {
            if (isset($this->formatters[$formatterPattern]) === false) {
                continue; // TODO: Report..
            }

            $formatterFunction = $this->formatters[$formatterPattern];

            $callbacks['/' . $formatterPattern . '/'] = function () use ($formatterFunction, $path, $formatterPattern) {
                try {
                    $result = $formatterFunction($path);
                } catch (\Exception $e) {
                    throw new RuntimeException("The format: [$formatterPattern] failed with message: " . $e->getMessage());
                }

                return $result;
            };
        }

        return preg_replace_callback_array($callbacks, $format);
    }

    /**
     * Returns exif data (cached)
     *
     * @param string $file
     *
     * @return array
     */
    private function exif(string $file): array
    {
        if (isset($this->exifCache[$file])) {
            return $this->exifCache[$file];
        }

        $data = [];

        if (function_exists('exif_read_data')) {
            $data = @exif_read_data($file);
        }

        if (is_array($data) === false) {
            $data = [];
        }

        $this->exifCache[$file] = $data;

        return $data;
    }

    /*
     * Registers all the formatters
     *
     * TODO: Make into a more dynamic system with classes etc
     */
    protected function setupFormatters()
    {
        $this->formatters = [
            ':date' => function ($path) {
                return $this->format(':year-:monthnum-:day', $path);
            },
            ':time' => function ($path) {
                return $this->format(':hour::minute::second', $path);
            },

            ':hour' => function ($path) {
                $exif = $this->exif($path);

                if (isset($exif['DateTimeOriginal']) && $time = strtotime($exif['DateTimeOriginal'])) {
                    return date('H', $time);
                }

                return date('H', filemtime($path));
            },

            ':dirname' => function ($path) {
                return basename(pathinfo($path, PATHINFO_DIRNAME));
            },

            ':minute' => function ($path) {
                $exif = $this->exif($path);

                if (isset($exif['DateTimeOriginal']) && $time = strtotime($exif['DateTimeOriginal'])) {
                    return date('i', $time);
                }

                return date('i', filemtime($path));
            },

            ':second' => function ($path) {
                $exif = $this->exif($path);

                if (isset($exif['DateTimeOriginal']) && $time = strtotime($exif['DateTimeOriginal'])) {
                    return date('s', $time);
                }

                return date('s', filemtime($path));
            },

            ':year' => function ($path) {
                // Check exif first
                $exif = $this->exif($path);

                if (isset($exif['DateTimeOriginal']) && $time = strtotime($exif['DateTimeOriginal'])) {
                    return date('Y', $time);
                }

                return date('Y', filemtime($path));
            },

            ':month' => function ($path) {
                return $this->format(':monthnum - :monthname', $path);
            },

            ':monthname' => function ($path) {
                $exif = $this->exif($path);

                if (isset($exif['DateTimeOriginal']) && $time = strtotime($exif['DateTimeOriginal'])) {
                    return date('F', $time);
                }

                return date('F', filemtime($path));
            },

            ':monthnum' => function ($path) {
                $exif = $this->exif($path);

                if (isset($exif['DateTimeOriginal']) && $time = strtotime($exif['DateTimeOriginal'])) {
                    return date('m', $time);
                }

                return date('m', filemtime($path));
            },

            ':day' => function ($path) {
                $exif = $this->exif($path);

                if (isset($exif['DateTimeOriginal']) && $time = strtotime($exif['DateTimeOriginal'])) {
                    return date('d', $time);
                }

                return date('d', filemtime($path));
            },

            ':devicemake' => function ($path) {
                $exif = $this->exif($path);

                return $exif['Make'] ?? '';
            },

            ':device' => function ($path) {
                $make = $this->format(':devicemake', $path);
                $model = $this->format(':devicemodel', $path);

                if (empty($make) && empty($model)) {
                    return 'Unknown';
                }

                if (empty($make)) {
                    return $model;
                }

                if (empty($model)) {
                    return $make;
                }

                return $make. ' ' .$model;
            },

            ':devicemodel' => function ($path) {
                $exif = $this->exif($path);

                return $exif['Model'] ?? '';
            },

            ':ext' => function ($path) {
                $extension = pathinfo($path, PATHINFO_EXTENSION);

                if ($extension) {
                    return ".$extension";
                }

                return '';
            },

            ':name' => function ($path) {
                return pathinfo($path, PATHINFO_FILENAME);
            }
        ];
    }
}
