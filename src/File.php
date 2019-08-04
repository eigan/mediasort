<?php

namespace Eigan\Mediasort;

class File
{
    /**
     * @var string
     */
    private $path;

    const TYPE_IMAGE = 'image';

    const TYPE_VIDEO = 'video';

    const TYPE_AUDIO = 'audio';

    const TYPE_UNKNOWN = 'unknown';

    const IMAGE_EXTENSIONS = [
        'bmp', 'cgm', 'g3', 'gif', 'ief', 'jpeg', 'jpg', 'jpe', 'ktx', 'png', 'btif', 'sgi',
        'svg', 'svgz', 'tiff', 'tif', 'psd', 'uvi', 'uvvi', 'uvg', 'uvvg', 'sub', 'djvu',
        'djv', 'dwg', 'dxf', 'fbs', 'fpx', 'fst', 'mmr', 'rlc', 'mdi', 'wdp', 'npx', 'wbmp',
        'xif', 'webp', '3ds', 'ras', 'cmx', 'fh', 'fhc', 'fh4', 'fh5', 'fh7', 'ico', 'sid',
        'pcx', 'pic', 'pct', 'pnm', 'pbm', 'pgm', 'ppm', 'rgb', 'tga', 'xbm', 'xpm', 'xwd',
        'arw'
    ];

    const VIDEO_EXTENSIONS = [
        '3gp', '3g2', 'h261', 'h263', 'h264', 'jpgv', 'jpm', 'jpgm', 'mj2', 'mjp2', 'mp4',
        'mp4v', 'mpg4', 'mpeg', 'mpg', 'mpe', 'm1v', 'm2v', 'ogv', 'qt', 'mov', 'uvh', 'uvvh',
        'uvm', 'uvvm', 'uvp', 'uvvp', 'uvs', 'uvvs', 'uvv', 'uvvv', 'dvb', 'fvt', 'mxu', 'm4u',
        'pyv', 'uvu', 'uvvu', 'viv', 'webm', 'f4v', 'fli', 'flv', 'm4v', 'mkv', 'mk3d', 'mks',
        'mng', 'mts', 'asf', 'asx', 'vob', 'wm', 'wmv', 'wmx', 'wvx', 'avi', 'movie', 'smv'
    ];

    const AUDIO_EXTENSIONS = [
        'adp', 'au', 'snd', 'mid', 'midi', 'kar', 'rmi', 'mp4a', 'mpga', 'mp2', 'mp2a', 'mp3',
        'm2a', 'm3a', 'oga', 'ogg', 'spx', 's3m', 'sil', 'uva', 'uvva', 'eol', 'dra', 'dts',
        'dtshd', 'lvp', 'pya', 'ecelp4800', 'ecelp7470', 'ecelp9600', 'rip', 'weba', 'aac',
        'aif', 'aiff', 'aifc', 'caf', 'flac', 'mka', 'm3u', 'wax', 'wma', 'ram', 'ra', 'rmp',
        'wav', 'xm'
    ];

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function getName()
    {
        return pathinfo($this->path, PATHINFO_FILENAME);
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getExtension()
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    public function getDirectoryName()
    {
        return basename(pathinfo($this->path, PATHINFO_DIRNAME));
    }

    public function isReadable()
    {
        return is_readable($this->path);
    }

    public function getSize()
    {
        return filesize($this->path);
    }

    public function getMimeType()
    {
        return mime_content_type($this->path);
    }

    public function getType()
    {
        $extension = strtolower($this->getExtension());

        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return self::TYPE_IMAGE;
        }

        if (in_array($extension, self::VIDEO_EXTENSIONS, true)) {
            return self::TYPE_VIDEO;
        }

        if (in_array($extension, self::AUDIO_EXTENSIONS, true)) {
            return self::TYPE_AUDIO;
        }

        return self::TYPE_UNKNOWN;
    }
}
