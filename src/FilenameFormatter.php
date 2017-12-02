<?php

namespace Eigan\Mediasort;

use \getid3_lib;
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

    /**
     * @var \getID3
     */
    private $id3Engine;

    public function __construct($useExif = true)
    {
        $this->useExif = $useExif;
        $this->id3Engine = new \getID3();

        $this->setupFormatters();
    }

    public function setUseExif(bool $useExif)
    {
        $this->useExif = $useExif;
    }

    /**
     * Sets a formatter, note that you can actually override another formatter here.
     *
     * @param string $name
     *
     * @param callable $formatter
     */
    public function setFormatter(string $name, callable $formatter)
    {
        $this->formatters[$name] = $formatter;
    }

    /**
     * @return string[]
     */
    public function getFormats(): array
    {
        return array_keys($this->formatters);
    }

    /**
     * Formats the given path into the format. Note that path should exist
     *
     * @param string $format
     * @param File   $file
     *
     * @return string
     *
     * @throws RuntimeException         When a formatter fails
     * @throws InvalidArgumentException When the formatter doesn't exist
     */
    public function format(string $format, File $file): string
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

            $callbacks['/' . $formatterPattern . '/'] = function () use ($formatterFunction, $file, $formatterPattern) {
                try {
                    $result = $formatterFunction($file);
                } catch (\Exception $e) {
                    throw new RuntimeException("The format: [$formatterPattern] failed with message: " . $e->getMessage());
                }

                return $result;
            };
        }

        $result = preg_replace_callback_array($callbacks, $format);

        if (count($this->cachedDate) > 2) {
            $this->cachedDate = [];
        }

        if (count($this->cachedExif) > 2) {
            $this->cachedExif = [];
        }

        return $result;
    }

    /**
     * Returns exif data (cached)
     *
     * @param File $file
     *
     * @return array
     */
    private function exif(File $file): array
    {
        if ($this->useExif === false) {
            return [];
        }
        
        if (isset($this->cachedExif[$file->getPath()])) {
            return $this->cachedExif[$file->getPath()];
        }

        $data = [];

        if (function_exists('exif_read_data')) {
            $data = @exif_read_data($file->getPath());
        }

        if (is_array($data) === false) {
            $data = [];
        }

        $this->cachedExif[$file->getPath()] = $data;

        return $data;
    }

    /**
     * @param File $file
     *
     * @return \DateTime|null
     */
    private function parseExifDate(File $file)
    {
        $exif = $this->exif($file);

        if (isset($exif['DateTimeOriginal']) && $time = strtotime($exif['DateTimeOriginal'])) {
            return $this->cachedDate[$file->getPath()] = new \DateTime($exif['DateTimeOriginal']);
        }

        if (isset($exif['DateTime'])) {
            return $this->cachedDate[$file->getPath()] = new \DateTime($exif['DateTime']);
        }

        return null;
    }

    private function id3(File $file)
    {
        $data = $this->id3Engine->analyze($file->getPath());

        getid3_lib::CopyTagsToComments($data);

        return $data;
    }

    /**
     * @param File $file
     *
     * @return \DateTime|null
     */
    private function parseId3Date(File $file)
    {
        $id3 = $this->id3($file);
        $date = null;

        if (isset($id3['quicktime']['moov']['subatoms']) && is_array($id3['quicktime']['moov']['subatoms'])) {
            foreach ($id3['quicktime']['moov']['subatoms'] as $subatom) {
                if (isset($subatom['creation_time_unix']) === false) {
                    continue;
                }

                $date = new \DateTime('@'.$subatom['creation_time_unix']);
                $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));

                break;
            }
        }

        return $date;
    }

    /**
     * @param File $file
     *
     * @return \DateTime|null
     */
    private function parseFilenameDate(File $file)
    {
        $datePatterns = [
            "/(?P<year>\d{4})(?P<month>\d{2})(?P<day>\d{2})_(?P<hour>\d{2})(?P<minute>\d{2})(?P<second>\d{2})/",
            "/(?P<year>\d{4})-(?P<month>\d{2})-(?P<day>\d{2}) (?P<hour>\d{2}).(?P<minute>\d{2}).(?P<second>\d{2})/",
            "/(?P<year>\d{4})(?P<month>\d{2})(?P<day>\d{2})(?P<hour>\d{2})(?P<minute>\d{2})(?P<second>\d{2})/",
            "/(?P<year>\d{4})(?P<month>\d{2})(?P<day>\d{2})-(?P<hour>\d{2})(?P<minute>\d{2})(?P<second>\d{2})/",
        ];

        foreach ($datePatterns as $datePattern) {
            preg_match($datePattern, $file->getPath(), $matches);

            if ($matches && $matches['year'] <= date('Y')) {
                try {
                    return new \DateTime("{$matches['year']}-{$matches['month']}-{$matches['day']} {$matches['hour']}:{$matches['minute']}:{$matches['second']}");
                } catch (\Exception $e) {
                    // Probably tried to parse a long number sequence, and failed with month = 13+ or something
                    continue;
                }
            }
        }

        return null;
    }

    private function parseDate(File $file): \DateTime
    {
        if (isset($this->cachedDate[$file->getPath()])) {
            return $this->cachedDate[$file->getPath()];
        }

        $date = null;

        if ($file->getType() === File::TYPE_IMAGE) {
            $date = $this->parseExifDate($file);
        }

        if (in_array($file->getType(), [File::TYPE_AUDIO, File::TYPE_VIDEO], true)) {
            $date = $this->parseId3Date($file);
        }

        if ($date === null) {
            $date = $this->parseFilenameDate($file);
        }

        if ($date === null) {
            // Failed to find date in exif and id3, we could fallback to modified time
            // but this date is often not what you want.
            throw new RuntimeException('Failed to find date.');
        }

        return $this->cachedDate[$file->getPath()] = $date;
    }

    /*
     * Registers all the formatters
     *
     * TODO: Make into a more dynamic system with classes etc
     */
    protected function setupFormatters()
    {
        $this->formatters = [
            ':date' => function (File $file) {
                try {
                    return $this->format(':year-:monthnum-:day', $file);
                } catch (\RuntimeException $e) {
                    throw new RuntimeException('Failed to find date.');
                }
            },
            ':time' => function (File $file) {
                try {
                    return $this->format(':hour::minute::second', $file);
                } catch (\RuntimeException $e) {
                    throw new RuntimeException('Failed to find date.');
                }
            },

            ':hour' => function (File $file) {
                $date = $this->parseDate($file);

                return $date->format('H');
            },

            ':dirname' => function (File $file) {
                return $file->getDirectoryName();
            },

            ':minute' => function (File $file) {
                $date = $this->parseDate($file);

                return $date->format('i');
            },

            ':second' => function (File $file) {
                $date = $this->parseDate($file);

                return $date->format('s');
            },

            ':year' => function (File $file) {
                // Check exif first
                $date = $this->parseDate($file);

                return $date->format('Y');
            },

            ':month' => function (File $file) {
                return $this->format(':monthnum - :monthname', $file);
            },

            ':monthname' => function (File $file) {
                $date = $this->parseDate($file);

                return $date->format('F');
            },

            ':monthnum' => function (File $file) {
                $date = $this->parseDate($file);

                return $date->format('m');
            },

            ':day' => function (File $file) {
                $date = $this->parseDate($file);

                return $date->format('d');
            },

            ':devicemake' => function (File $file) {
                $exif = $this->exif($file);

                $make = $exif['Make'] ?? '';

                if (empty($make)) {
                    throw new RuntimeException('Did not find maker');
                }

                return $make;
            },

            ':device' => function (File $file) {
                try {
                    $make = $this->format(':devicemake', $file);
                } catch (RuntimeException $e) {
                    $make = '';
                }

                try {
                    $model = $this->format(':devicemodel', $file);
                } catch (RuntimeException $e) {
                    $model = '';
                }

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

            ':devicemodel' => function (File $file) {
                $exif = $this->exif($file);

                $model = $exif['Model'] ?? '';

                if (empty($model)) {
                    throw new RuntimeException('Did not find device model');
                }

                return $model;
            },

            ':ext' => function (File $file) {
                $extension = $file->getExtension();

                if ($extension) {
                    return ".$extension";
                }

                return '';
            },

            ':name' => function (File $file) {
                return $file->getName();
            },

            ':artist' => function (File $file) {
                $id3 = $this->id3($file);

                $artist = null;

                if (isset($id3['comments']['artist']) && is_array($id3['comments']['artist'])) {
                    $artist = reset($id3['comments']['artist']);
                }

                if ($artist === null) {
                    throw new RuntimeException('Did not find artist in id3 tags.');
                }

                return $artist;
            },

            ':track' => function (File $file) {
                $id3 = $this->id3($file);

                $title = null;

                if (isset($id3['comments']['title']) && is_array($id3['comments']['title'])) {
                    $title = reset($id3['comments']['title']);
                }

                if (isset($id3['comments']['track_number']) && is_array($id3['comments']['track_number'])) {
                    $trackNumber = reset($id3['comments']['track_number']);

                    if (strpos($trackNumber, '/') !== false) {
                        list($trackNumber, $totalTracks) = explode('/', $trackNumber);
                    }

                    $title = sprintf('%02d', $trackNumber) . ' - ' . $title;
                }

                if ($title === null) {
                    throw new RuntimeException('Did not find title in id3 tags.');
                }

                return $title;
            },

            ':album' => function (File $file) {
                $id3 = $this->id3($file);

                $album = null;

                if (isset($id3['comments']['album']) && is_array($id3['comments']['album'])) {
                    $album = reset($id3['comments']['album']);
                }

                if ($album === null) {
                    throw new RuntimeException('Did not find album in id3 tags.');
                }

                return $album;
            }
        ];
    }
}
