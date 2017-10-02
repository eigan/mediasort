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
                return $this->format(':day.:monthnumeric.:year', $path);
            },
            ':time' => function ($path) {
                return $this->format(':hour::minute::second', $path);
            },

            ':hour' => function ($path) {
                return date('H', filemtime($path));
            },

            ':minute' => function ($path) {
                return date('i', filemtime($path));
            },

            ':second' => function ($path) {
                return date('s', filemtime($path));
            },

            ':year' => function ($path) {
                // Check exif first
                $exif = $this->exif($path);

                if (isset($exif['DateTimeOriginal'])) {
                    return date('Y', strtotime($exif['DateTimeOriginal']));
                }

                return date('Y', filemtime($path));
            },

            ':month' => function ($path) {
                return $this->format(':monthnumeric - :monthstring', $path);
            },

            ':monthstring' => function ($path) {
                $exif = $this->exif($path);

                if (isset($exif['DateTimeOriginal'])) {
                    return date('F', strtotime($exif['DateTimeOriginal']));
                }

                return date('F', filemtime($path));
            },

            ':monthnumeric' => function ($path) {
                $exif = $this->exif($path);

                if (isset($exif['DateTimeOriginal'])) {
                    return date('m', strtotime($exif['DateTimeOriginal']));
                }

                return date('m', filemtime($path));
            },

            ':day' => function ($path) {
                $exif = $this->exif($path);

                if (isset($exif['DateTimeOriginal'])) {
                    return date('d', strtotime($exif['DateTimeOriginal']));
                }

                return date('d', filemtime($path));
            },

            ':exifyear' => function ($path) {
            },

            ':original' => function ($path) {
                return pathinfo($path)['filename'];
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

        $data = @exif_read_data($file);

        if (is_array($data) === false) {
            $data = [];
        }

        $this->exifCache[$file] = $data;

        return $data;
    }
}
