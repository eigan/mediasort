<?php

namespace Eigan\Mediasort\Tests\Unit;

use Eigan\Mediasort\FilenameFormatter;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

class FilenameFormatterTest extends TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    protected $directory;

    /**
     * @var string
     */
    protected $filePath;

    /**
     * Pattern: YYYYMMDD_HHMMSS
     *
     * @var string
     */
    protected $datedPath;

    /**
     * Pattern: YYYY-MM-DD HH-MM-SS
     *
     * @var string
     */
    protected $datedPath2;

    /**
     * Pattern: YYYYMMDDHHMMSS
     *
     * @var string
     */
    protected $datedPath3;

    /**
     * Pattern: YYYYMMDD-HHMMSS
     *
     * @var string
     */
    protected $datedPath4;

    /**
     * Pattern: Many numbers..
     *
     * @var string
     */
    protected $numberedPath;

    /**
     * @var FilenameFormatter
     */
    protected $formatter;

    public function setUp()
    {
        $this->directory =  vfsStream::setup('test', null, [
            'source' => [
                'myfile.jpg' => 'content',
                'other' => '',
                'VID_20170709_121346.mp4' => 'content2',
                '2017-07-09 12.13.46.mp4' => 'content3',
                'VID_20170709121346.mp4' => 'content4',
                '123420169123456789.mp4' => 'content5',
                'VID_20170518-222741.mp4' => 'content6',
            ]
        ]);

        $this->filePath = $this->directory->url() . '/source/myfile.jpg';
        $this->datedPath = $this->directory->url() . '/source/VID_20170709_121346.mp4';
        $this->datedPath2 = $this->directory->url() . '/source/2017-07-09 12.13.46.mp4';
        $this->datedPath3 = $this->directory->url() . '/source/VID_20170709121346.mp4';
        $this->datedPath4 = $this->directory->url() . '/source/VID_20170518-222741.mp4';
        $this->numberedPath = $this->directory->url() . '/source/123420169123456789.mp4';

        $this->formatter = new FilenameFormatter();
    }

    public function testNotValidFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No valid formats');

        $this->formatter->format('invalid', $this->filePath);
    }

    public function testInvalidFormat()
    {
        $this->assertEquals('myfile:invalid', $this->formatter->format(':name:invalid', $this->filePath));
    }

    public function testFailingFormat()
    {
        $this->formatter->setFormatter(':crash', function ($path) {
            throw new \RuntimeException('I am a crasher!');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The format: [:crash] failed with message: I am a crasher!');

        $this->formatter->format(':crash', $this->filePath);
    }

    public function testDate()
    {
        $date = strtotime('-1 month 4 days');
        touch($this->filePath, $date);

        $this->assertEquals(date('Y-m-d', $date), $this->formatter->format(':date', $this->filePath));
    }

    public function testTime()
    {
        $time = strtotime('-1 month 4 days');
        touch($this->filePath, $time);

        $this->assertEquals(date('H:i:s', $time), $this->formatter->format(':time', $this->filePath));
    }

    public function testDirname()
    {
        $this->assertEquals('source', $this->formatter->format(':dirname', $this->filePath));
    }

    public function testMonth()
    {
        $date = strtotime('-1 month');
        touch($this->filePath, $date);

        $this->assertEquals(date('m - F', $date), $this->formatter->format(':month', $this->filePath));
    }

    public function testMonthstring()
    {
        $date = strtotime('-1 month');
        touch($this->filePath, $date);

        $this->assertEquals(date('F', $date), $this->formatter->format(':monthname', $this->filePath));
    }

    public function testName()
    {
        $this->assertEquals('myfile', $this->formatter->format(':name', $this->filePath));
    }

    public function testTimeFromPath()
    {
        $this->assertEquals('12:13:46', $this->formatter->format(':time', $this->datedPath));
        $this->assertEquals('12:13:46', $this->formatter->format(':time', $this->datedPath2));
        $this->assertEquals('12:13:46', $this->formatter->format(':time', $this->datedPath3));
        $this->assertEquals('22:27:41', $this->formatter->format(':time', $this->datedPath4));

        $date = strtotime('-1 month');
        touch($this->numberedPath, $date);

        $this->assertEquals(date('H:i:s', $date), $this->formatter->format(':time', $this->numberedPath));
    }

    public function testDateFromPath()
    {
        $this->assertEquals('2017-07-09', $this->formatter->format(':date', $this->datedPath));
        $this->assertEquals('2017-07-09', $this->formatter->format(':date', $this->datedPath2));
        $this->assertEquals('2017-07-09', $this->formatter->format(':date', $this->datedPath3));
        $this->assertEquals('2017-05-18', $this->formatter->format(':date', $this->datedPath4));

        $date = strtotime('-1 month');
        touch($this->numberedPath, $date);

        $this->assertEquals(date('Y-m-d', $date), $this->formatter->format(':date', $this->numberedPath));
    }

    public function testExifTime()
    {
        $this->setupExif();

        $this->assertEquals('17:49:56', $this->formatter->format(':time', __DIR__ . '/../exif.jpg'));
    }

    public function testExifDate()
    {
        $this->setupExif();

        $this->assertEquals('2017-06-21', $this->formatter->format(':date', __DIR__ . '/../exif.jpg'));
    }

    public function testExifMonth()
    {
        $this->setupExif();

        $this->assertEquals('06 - June', $this->formatter->format(':month', __DIR__ . '/../exif.jpg'));
    }

    public function testExifDevice()
    {
        $this->setupExif();

        $this->assertEquals('Google Pixel', $this->formatter->format(':device', __DIR__ . '/../exif.jpg'));
    }

    public function testExifDeviceNoModel()
    {
        $this->setupExif();

        $this->formatter->setFormatter(':devicemodel', function ($path) {
            return '';
        });

        $this->assertEquals('Google', $this->formatter->format(':device', __DIR__ . '/../exif.jpg'));
    }

    public function testExifDeviceNoMake()
    {
        $this->setupExif();

        $this->formatter->setFormatter(':devicemake', function ($path) {
            return '';
        });

        $this->assertEquals('Pixel', $this->formatter->format(':device', __DIR__ . '/../exif.jpg'));
    }

    public function testExifDeviceNoMakeModel()
    {
        $this->setupExif();

        $this->formatter->setFormatter(':devicemake', function ($path) {
            return '';
        });

        $this->formatter->setFormatter(':devicemodel', function ($path) {
            return '';
        });

        $this->assertEquals('Unknown', $this->formatter->format(':device', __DIR__ . '/../exif.jpg'));
    }

    private function setupExif()
    {
        vfsStream::copyFromFileSystem(__DIR__ . '/..', $this->directory, 4318847);
    }
}
