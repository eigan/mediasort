<?php

namespace Eigan\Mediasort;

use InvalidArgumentException;
use RuntimeException;

class FilenameFormatter
{
    /**
     * @var Callable[]
     */
    private $formatters;

    /**
     * Cache path -> exif result
     *
     * @var array
     */
    private $cachedExif;

    /**
     * Cache path -> DateTime
     *
     * @var \DateTime[]
     */
    private $cachedDate = [];

    /**
     * @var bool
     */
    private $useExif;

    public function __construct($useExif = true)
    {
        $this->useExif = $useExif;
        $this->setupFormatters();
    }

    public function setUseExif(bool $useExif)
    {
        $this->useExif = $useExif;
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
        if ($this->useExif === false) {
            return [];
        }
        
        if (isset($this->cachedExif[$file])) {
            return $this->cachedExif[$file];
        }

        $data = [];

        if (function_exists('exif_read_data')) {
            $data = @exif_read_data($file);
        }

        if (is_array($data) === false) {
            $data = [];
        }

        $this->cachedExif[$file] = $data;

        return $data;
    }

    private function parseDate(string $path): \DateTime
    {
        if (isset($this->cachedDate[$path])) {
            return $this->cachedDate[$path];
        }

        $exif = $this->exif($path);

        if (isset($exif['DateTimeOriginal']) && $time = strtotime($exif['DateTimeOriginal'])) {
            return $this->cachedDate[$path] = new \DateTime('@'.$time);
        }

        if (isset($exif['DateTime']) && $time = strtotime($exif['DateTime'])) {
            return $this->cachedDate[$path] = new \DateTime('@'.$time);
        }

        $datePatterns = [
            "/(?P<year>\d{4})(?P<month>\d{2})(?P<day>\d{2})_(?P<hour>\d{2})(?P<minute>\d{2})(?P<second>\d{2})/",
            "/(?P<year>\d{4})-(?P<month>\d{2})-(?P<day>\d{2}) (?P<hour>\d{2}).(?P<minute>\d{2}).(?P<second>\d{2})/",
            "/(?P<year>\d{4})(?P<month>\d{2})(?P<day>\d{2})(?P<hour>\d{2})(?P<minute>\d{2})(?P<second>\d{2})/",
            "/(?P<year>\d{4})(?P<month>\d{2})(?P<day>\d{2})-(?P<hour>\d{2})(?P<minute>\d{2})(?P<second>\d{2})/",
        ];

        foreach ($datePatterns as $datePattern) {
            preg_match($datePattern, $path, $matches);

            if ($matches && $matches['year'] <= date('Y')) {
                try {
                    $date = new \DateTime("{$matches['year']}-{$matches['month']}-{$matches['day']} {$matches['hour']}:{$matches['minute']}:{$matches['second']}");

                    return $this->cachedDate[$path] = $date;
                } catch (\Exception $e) {
                    // Probably tried to parse a long number sequence, and failed with month = 13+ or something
                    continue;
                }
            }
        }

        return $this->cachedDate[$path] = new \DateTime('@'.filemtime($path));
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
                $date = $this->parseDate($path);

                return $date->format('H');
            },

            ':dirname' => function ($path) {
                return basename(pathinfo($path, PATHINFO_DIRNAME));
            },

            ':minute' => function ($path) {
                $date = $this->parseDate($path);

                return $date->format('i');
            },

            ':second' => function ($path) {
                $date = $this->parseDate($path);

                return $date->format('s');
            },

            ':year' => function ($path) {
                // Check exif first
                $date = $this->parseDate($path);

                return $date->format('Y');
            },

            ':month' => function ($path) {
                return $this->format(':monthnum - :monthname', $path);
            },

            ':monthname' => function ($path) {
                $date = $this->parseDate($path);

                return $date->format('F');
            },

            ':monthnum' => function ($path) {
                $date = $this->parseDate($path);

                return $date->format('m');
            },

            ':day' => function ($path) {
                $date = $this->parseDate($path);

                return $date->format('d');
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
