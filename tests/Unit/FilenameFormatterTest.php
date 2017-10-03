<?php

namespace Eig\PrettyTree\Tests\Unit;

use Eig\PrettyTree\FilenameFormatter;
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
     * @var FilenameFormatter
     */
    protected $formatter;

    public function setUp()
    {
        $this->directory =  vfsStream::setup('test', null, [
            'source' => [
                'myfile.jpg' => 'content',
                'other' => ''
            ]
        ]);

        $this->filePath = $this->directory->url() . '/source/myfile.jpg';
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

        $this->assertEquals(date('F', $date), $this->formatter->format(':monthstring', $this->filePath));
    }

    public function testOriginal()
    {
        $this->assertEquals('myfile.jpg', $this->formatter->format(':original', $this->filePath));
        $this->assertEquals('other', $this->formatter->format(':original', $this->directory->url() . '/source/other'));
    }

    public function testName()
    {
        $this->assertEquals('myfile', $this->formatter->format(':name', $this->filePath));
    }
}
