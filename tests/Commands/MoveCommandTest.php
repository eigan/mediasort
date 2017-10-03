<?php

namespace Eig\PrettyTree\Tests\Commands;

use Eig\PrettyTree\Application;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
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
            'destination' => $directory->url() . '/destination',
        ]);

        $this->assertContains('Path ['.$directory->url() . '/source2] does not exist', $output);
    }

    public function testNoDestinationInput()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'myfile'
            ]
        ]);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            '--format' => ':day:ext'
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertContains('Destination: ' . $directory->url() . '/source', $output);
        $this->assertFileExists($directory->url() . '/source/' . date('d') . '.jpg');
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

            '-r' => true,
            '--format' => ':year/:month/:day:ext'
        ], ['interactive' => false, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $destinationPath = $directory->url() . '/destination/2017/10 - October/'.date('d').'.jpg';

        $this->assertFileNotExists($directory->url() . '/source/myfile.jpg');
        $this->assertFileExists($directory->url() . '/source/nested/OtherFilename.jpg');
        $this->assertEquals('content', file_get_contents($directory->url() . '/source/nested/OtherFilename.jpg'));

        $this->assertFileExists($destinationPath);

        $this->assertEquals('content', file_get_contents($destinationPath));
        $this->assertContains('Skipped: Duplicate', $output);
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

    public function testIgnore()
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

            '--ignore' => 'txt,php,'
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

    public function testNotRecursive()
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

            '-r' => true
        ]);

        $root = $directory->url();
        $this->assertFileExists($root . '/destination/sub/subfile.txt');
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

    public function testHomeDirResolve()
    {
        if (function_exists('posix_getuid') === false) {
            $this->markTestSkipped();
        }

        $homeInfo = posix_getpwuid(posix_getuid());

        $homeInfo['dir'] = trim($homeInfo['dir'], '/');

        $dir = explode('/', $homeInfo['dir']);

        $result = [];
        $structure = [];

        foreach ($dir as $dir2) {
            foreach ($result as $key => &$derp) {
                if ($key != $dir2) {
                    unset($result[$key]);
                }
            }
            $result[$dir2] = [];

            $structure[$dir2] = &$result;

            $result = &$result[$dir2];
        }

        $result = [
            'source' => [],
            'destination' => []
        ];

        vfsStream::setup(reset($dir), null, reset($structure[reset($dir)]));

        $output = $this->execute([
            'source' => 'vfs://~/source',
            'destination' => 'vfs://~/destination'
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertContains($homeInfo['dir'], $output);
    }

    /**
     * This doesnt work with vfs.. For know we just expect to crash
     *
     * @expectedException \PHPUnit\Framework\Error\Warning
     */
    public function testLink()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'myfile'
            ],
            'destination' => [

            ]
        ]);

        $this->expectExceptionMessage('link(): No such file or directory');

        $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',

            '--link' => true
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
    }

    public function testVerboseMode()
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

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',

            '-r' => true
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertContains('Source: ' . $directory->url() . '/source', $output);
        $this->assertContains('Destination: ' . $directory->url() . '/destination', $output);
        $this->assertContains('File: ' . $directory->url() . '/source/myfile.jpg', $output);
    }

    public function testInteractive()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'content',
                'tobeskipped.jpg' => 'content2'
            ],
            'destination' => [

            ]
        ]);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',

            '-r' => true
        ], ['interactive' => true], ['Yes', 'N']);

        $this->assertContains('Move? (yes/no) [yes]', $output);

        // Answer: yes, move
        $this->assertFileExists($directory->url() . '/destination/myfile.jpg');

        // Answer: N, Left unchanged
        $this->assertFileNotExists($directory->url() . '/destination/tobeskipped.jpg');
        $this->assertContains('content2', file_get_contents($directory->url() . '/source/tobeskipped.jpg'));
    }

    public function testFailingFormat()
    {
        $application = new Application();

        $command = $application->find('move');

        $commandTester = new CommandTester($command);

        $application->getFilenameFormatter()->setFormatter(':crash', function ($path) {
            throw new \RuntimeException('I am a crasher!');
        });

        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'content',
                'other' => 'content2'
            ],
            'destination' => [

            ]
        ]);

        $commandTester->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
            '--format' => ':crash:ext',
            '-v'
        ], ['interactive' => false]);

        $this->assertFileExists($directory->url() . '/source/myfile.jpg');
        $this->assertFileExists($directory->url() . '/source/other');
        $this->assertFileNotExists($directory->url() . '/destination/other');

        $output = $commandTester->getDisplay();
        $this->assertContains('The format: [:crash] failed with message: I am a crasher!', $output);
    }

    private function createDirectory($structure)
    {
        return vfsStream::setup('test', null, $structure);
    }

    private function execute($arguments, $options = [], $inputs = [])
    {
        $this->commandTester->setInputs($inputs);

        $this->commandTester->execute($arguments, $options + [
            'interactive' => false
        ]);

        return $this->commandTester->getDisplay();
    }
}
