<?php

namespace Eig\PrettyTree\Tests\Commands;

use Eig\PrettyTree\Application;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class MoveCommandTest extends TestCase
{
    /**
     * @var CommandTester
     */
    private $commandTester;

    private $root;

    public function setUp()
    {
        $application = new Application();
        $command = $application->find('move');

        $this->commandTester = new CommandTester($command);
    }

    public function testSourceNotExists()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'myfile'
            ],
            'destination' => [

            ]
        ]);

        $output = $this->execute([
            'source' => $directory->url() . '/source2',
            'destination' => $directory->url() . '/destination'
        ]);

        $this->assertContains('Path ['.$directory->url() . '/source2] does not exist', $output);
    }

    public function testResolve()
    {
        $root = $this->createDirectory([
            'home' => [
                'einar' => [
                    'pictures' => [
                        'source' => [
                            'myfile.jpg' => 'content'
                        ]
                    ],

                    'pretty' => [

                    ]
                ]
            ]
        ]);

        $application = new Application($root->url() . '/home/einar');
        $command = $application->find('move');

        $this->commandTester = new CommandTester($command);

        $this->commandTester->execute([
            'source' => 'pictures/source',
            'destination' => 'pretty'
        ], ['interactive' => false]);

        $this->assertFileExists($root->url() . '/home/einar/pretty/myfile.jpg');
        $this->assertFileNotExists($root->url() . '/home/einar/pictures/source/myfile.jpg');

        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testResolveWithDots()
    {
        $root = $this->createDirectory([
            'home' => [
                'einar' => [
                    'pictures' => [
                        'source' => [
                            'myfile.jpg' => 'content'
                        ]
                    ],

                    'pretty' => [

                    ]
                ]
            ]
        ]);

        $application = new Application($root->url() . '/home/einar/pictures');
        $command = $application->find('move');

        $this->commandTester = new CommandTester($command);

        $this->commandTester->execute([
            'source' => 'source',
            'destination' => '../pretty',
            '-r' => true
        ], ['interactive' => false]);

        $this->assertFileExists($root->url() . '/home/einar/pretty/myfile.jpg');
        $this->assertFileNotExists($root->url() . '/home/einar/pictures/source/myfile.jpg');
    }

    public function testDestinationNotExists()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'myfile'
            ],
            'destination' => [

            ]
        ]);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination2',
        ]);

        $this->assertContains('Path ['.$directory->url() . '/destination2] does not exist', $output);
    }

    public function testMoveSingleFile()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'myfile'
            ],
            'destination' => [

            ]
        ]);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',

            '--format' => ':year/:month/:name:ext'
        ]);

        $destinationPath = $directory->url() . '/destination/2017/10 - October/myfile.jpg';
        $this->assertFileNotExists($directory->url() . '/source/myfile.jpg');
        $this->assertFileExists($destinationPath);

        $this->assertEquals('myfile', file_get_contents($destinationPath));
    }

    public function testSkipDuplicate()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'content',
                'nested' => [
                    'OtherFilename.jpg' => 'content'
                ]
            ],
            'destination' => [

            ]
        ]);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',

            '--format' => ':year/:month/:day:ext'
        ], ['interactive' => false]);

        $destinationPath = $directory->url() . '/destination/2017/10 - October/'.date('d').'.jpg';

        $this->assertFileNotExists($directory->url() . '/source/myfile.jpg');
        $this->assertFileExists($directory->url() . '/source/nested/OtherFilename.jpg');
        $this->assertEquals('content', file_get_contents($directory->url() . '/source/nested/OtherFilename.jpg'));

        $this->assertFileExists($destinationPath);

        $this->assertEquals('content', file_get_contents($destinationPath));
    }

    public function testOnly()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'content',
                'myfile.JPG' => 'content2',
                'otherfile.txt' => 'hehehe',
                'myfile.php' => '<?php ?>',
                'empty' => ''
            ],
            'destination' => [

            ]
        ]);

        $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',

            '--only' => 'jpg,JPG'
        ]);

        $root = $directory->url();
        $this->assertFileExists($root . '/source/otherfile.txt');
        $this->assertFileExists($root . '/source/myfile.php');
        $this->assertFileExists($root . '/source/empty');
        $this->assertFileNotExists($root . '/source/myfile.jpg');
        $this->assertFileNotExists($root . '/source/myfile.JPG');

        $this->assertFileExists($root . '/destination/myfile.jpg');
        $this->assertFileExists($root . '/destination/myfile.JPG');
    }

    public function testRecursive()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'content',
                'sub' => [
                    'subfile.txt' => 'content'
                ]
            ],
            'destination' => [

            ]
        ]);

        $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',

            '-r' => false
        ]);

        $root = $directory->url();
        $this->assertFileExists($root . '/source/sub/subfile.txt');
    }

    public function testIncrementHit()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'content',
                'nested' => [
                    'myfile.jpg' => 'content2'
                ],
                'nested2' => ['myfile.jpg' => 'content3'],
                'myFile (1).jpg' => 'content6',
                'nested3' => ['myFile (1).jpg' => 'content5'],
                'noext' => 'noext1',
                'nested4' => ['noext' => 'noext2']
            ],
            'destination' => [

            ]
        ]);

        $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',

            '--format' => ':name:ext',
            '-r' => true
        ]);

        $root = $directory->url();

        $this->assertFileExists($root . '/destination/myfile.jpg');
        $this->assertFileExists($root . '/destination/myfile (1).jpg');
        $this->assertFileExists($root . '/destination/myfile (2).jpg');
        $this->assertFileExists($root . '/destination/myFile (1).jpg');
        $this->assertFileExists($root . '/destination/myFile (1) (1).jpg');
        $this->assertFileExists($root . '/destination/noext');
        $this->assertFileExists($root . '/destination/noext (1)');
    }

    /*
    public function testLink()
    {
        $this->markTestIncomplete('Using link() is not supported with the Virtual File System');

        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'myfile'
            ],
            'destination' => [

            ]
        ]);

        $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',

            '--link' => 'test'
        ]);

        $root = $directory->url();

        $this->assertFileExists($root . '/source/myfile.jpg');
        $this->assertFileExists($root . '/destination/myfile.jpg');
    }*/

    private function createDirectory($structure)
    {
        return vfsStream::setup('test', null, $structure);
    }

    private function execute($arguments)
    {
        $this->commandTester->execute($arguments, [
            'interactive' => false
        ]);

        return $this->commandTester->getDisplay();
    }
}
