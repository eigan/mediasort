<?php

namespace Eig\PrettyTree;

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

    protected function setupFormatters()
    {
        $this->formatters = [
            ':date' => function ($path) {
                return $this->format(':year-:monthnumeric-:day', $path);
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
                return $this->format(':monthnumeric - :monthstring', $path);
            },

            ':monthstring' => function ($path) {
                $exif = $this->exif($path);

                if (isset($exif['DateTimeOriginal']) && $time = strtotime($exif['DateTimeOriginal'])) {
                    return date('F', $time);
                }

                return date('F', filemtime($path));
            },

            ':monthnumeric' => function ($path) {
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

            ':original' => function ($path) {
                return pathinfo($path, PATHINFO_FILENAME) . $this->format(':ext', $path);
            },

            ':device:make' => function ($path) {
                $exif = $this->exif($path);

                return $exif['Make'] ?? '';
            },

            ':device' => function ($path) {
                $make = $this->format('device:make', $path);
                $model = $this->format('device:model', $path);

                if (empty($make) && empty($model)) {
                    return 'Unknown';
                }

                if (empty($make)) {
                    return $model;
                }

                if (empty($model)) {
                    return $make;
                }

                return $make.$model;
            },

            ':device:model' => function ($path) {
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

    public function setFormatter($name, callable $formatter)
    {
        $this->formatters[$name] = $formatter;
    }

    public function format(string $format, string $path): string
    {
        $callbacks = [];

        preg_match_all('/\:([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', $format, $matches);

        if (isset($matches[0]) === false || empty($matches[0])) {
            throw new \InvalidArgumentException('No valid formats');
        }

        foreach ($matches[0] as $formatterPattern) {
            if (isset($this->formatters[$formatterPattern]) === false) {
                continue; // Report..
            }

            $formatterFunction = $this->formatters[$formatterPattern];

            $callbacks['/' . $formatterPattern . '/'] = function () use ($formatterFunction, $path, $formatterPattern) {
                try {
                    $result = $formatterFunction($path);
                } catch (\Exception $e) {
                    throw new \RuntimeException("The format: [$formatterPattern] failed with message: " . $e->getMessage());
                }

                return $result;
            };
        }

        return preg_replace_callback_array($callbacks, $format);
    }

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
}
