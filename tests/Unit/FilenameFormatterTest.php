<?php

namespace Eigan\Mediasort\Tests\Unit;

use Eigan\Mediasort\File;
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
     * Pattern: YYYYMMDD-HH:MM:SS
     *
     * @var string
     */
    protected $datedPath5;

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
                '123420169123456789 (1).mp4' => 'content5',
                'VID_20170518-222741 (1).mp4' => 'content6',
                '2017-07-09 12:13:46.mp4' => 'content7',
            ]
        ]);

        $this->filePath = $this->directory->url() . '/source/myfile.jpg';
        $this->datedPath = $this->directory->url() . '/source/VID_20170709_121346.mp4';
        $this->datedPath2 = $this->directory->url() . '/source/2017-07-09 12.13.46.mp4';
        $this->datedPath3 = $this->directory->url() . '/source/VID_20170709121346.mp4';
        $this->datedPath4 = $this->directory->url() . '/source/VID_20170518-222741 (1).mp4';
        $this->datedPath5 = $this->directory->url() . '/source/2017-07-09 12:13:46.mp4';
        $this->numberedPath = $this->directory->url() . '/source/123420169123456789 (1).mp4';

        $this->formatter = new FilenameFormatter();
    }

    public function testNotValidFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No valid formats');

        $this->formatter->format('invalid', new File($this->filePath));
    }

    public function testInvalidFormat()
    {
        $this->assertEquals('myfile:invalid', $this->formatter->format(':name:invalid', new File($this->filePath)));
    }

    public function testFailingFormat()
    {
        $this->formatter->setFormatter(':crash', function ($path) {
            throw new \RuntimeException('I am a crasher!');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The format: [:crash] failed with message: I am a crasher!');

        $this->formatter->format(':crash', new File($this->filePath));
    }

    public function testDirname()
    {
        $this->assertEquals('source', $this->formatter->format(':dirname', new File($this->filePath)));
    }

    public function testName()
    {
        $this->assertEquals('myfile', $this->formatter->format(':name', new File($this->filePath)));
    }

    public function testTimeFromPath()
    {
        $this->assertEquals('12:13:46', $this->formatter->format(':time', new File($this->datedPath)));
        $this->assertEquals('12:13:46', $this->formatter->format(':time', new File($this->datedPath2)));
        $this->assertEquals('12:13:46', $this->formatter->format(':time', new File($this->datedPath3)));
        $this->assertEquals('22:27:41', $this->formatter->format(':time', new File($this->datedPath4)));
        $this->assertEquals('12:13:46', $this->formatter->format(':time', new File($this->datedPath5)));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The format: [:time] failed with message: Failed to find date.');

        $this->formatter->format(':time', new File($this->numberedPath));
    }

    public function testDateFromPath()
    {
        $this->assertEquals('2017-07-09', $this->formatter->format(':date', new File($this->datedPath)));
        $this->assertEquals('2017-07-09', $this->formatter->format(':date', new File($this->datedPath2)));
        $this->assertEquals('2017-07-09', $this->formatter->format(':date', new File($this->datedPath3)));
        $this->assertEquals('2017-05-18', $this->formatter->format(':date', new File($this->datedPath4)));
        $this->assertEquals('2017-07-09', $this->formatter->format(':date', new File($this->datedPath5)));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The format: [:date] failed with message: Failed to find date.');

        $this->formatter->format(':date', new File($this->numberedPath));
    }

    public function testExifTime()
    {
        $this->setupExif();

        $this->assertEquals('17:49:56', $this->formatter->format(':time', new File(__DIR__ . '/../exif.jpg')));
        $this->assertEquals('17:49:56', $this->formatter->format(':time', new File(__DIR__ . '/../exif.jpg')));
    }

    public function testExifDate()
    {
        $this->setupExif();

        $this->assertEquals('2017-06-21', $this->formatter->format(':date', new File(__DIR__ . '/../exif.jpg')));
        $this->assertEquals('2017-07-02', $this->formatter->format(':date', new File(__DIR__ . '/../exif2.jpg')));
    }

    public function testExifMonth()
    {
        $this->setupExif();

        $this->assertEquals('06 - June', $this->formatter->format(':month', new File(__DIR__ . '/../exif.jpg')));
    }

    public function testExifDevice()
    {
        $this->setupExif();

        $this->assertEquals('Google Pixel', $this->formatter->format(':device', new File(__DIR__ . '/../exif.jpg')));
    }

    public function testExifDeviceNoModel()
    {
        $this->setupExif();

        $this->formatter->setFormatter(':devicemodel', function ($path) {
            return '';
        });

        $this->assertEquals('Google', $this->formatter->format(':device', new File(__DIR__ . '/../exif.jpg')));
    }

    public function testExifDeviceNoMake()
    {
        $this->setupExif();

        $this->formatter->setFormatter(':devicemake', function ($path) {
            return '';
        });

        $this->assertEquals('Pixel', $this->formatter->format(':device', new File(__DIR__ . '/../exif.jpg')));
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

        $this->assertEquals('Unknown', $this->formatter->format(':device', new File(__DIR__ . '/../exif.jpg')));
    }

    public function testId3Date()
    {
        $defaultTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Oslo');

        $this->assertEquals('20:07:58', $this->formatter->format(':time', new File(__DIR__ . '/../id3.mp4')));

        date_default_timezone_set($defaultTimezone);
    }

    public function testId3Artist()
    {
        $this->assertEquals('Einar', $this->formatter->format(':artist', new File(__DIR__ . '/../id3.mp4')));
    }

    public function testId3Album()
    {
        $this->assertEquals('Mediasort', $this->formatter->format(':album', new File(__DIR__ . '/../id3.mp4')));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The format: [:album] failed with message: Did not find album in id3 tags.');

        $this->formatter->format(':album', new File(__DIR__ . '/../id3_2.mp4'));
    }

    public function testId3Track()
    {
        $this->assertEquals('01 - track name æøå', $this->formatter->format(':track', new File(__DIR__ . '/../id3.mp4')));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The format: [:track] failed with message: Did not find title in id3 tags.');

        $this->formatter->format(':track', new File(__DIR__ . '/../id3_2.mp4'));
    }

    public function testId3NoArtist()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The format: [:artist] failed with message: Did not find artist in id3 tags.');
        $this->formatter->format(':artist', new File(__DIR__ . '/../id3_2.mp4'));
    }

    private function setupExif()
    {
        vfsStream::copyFromFileSystem(__DIR__ . '/..', $this->directory, 4318847);
    }
}
