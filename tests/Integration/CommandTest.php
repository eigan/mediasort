<?php

namespace Eig\PrettyTree\Tests\Integration;

use Eig\PrettyTree\Application;
use Eig\PrettyTree\Command;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class CommandTest extends TestCase
{
    /**
     * @var CommandTester
     */
    private $commandTester;

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

    public function testNoDestinationInput()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'myfile'
            ]
        ]);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            '--format' => ':day'
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertContains('Destination: ' . $directory->url() . '/source', $output);
        $this->assertFileExists($directory->url() . '/source/' . date('d') . '.jpg');
    }

    public function testResolveRelative()
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

    public function testResolveRelativeWithDots()
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

    public function testResolveHomeDir()
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

            '--format' => ':year/:month/:name'
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
            '--format' => ':year/:month/:day'
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

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',

            '--ignore' => 'txt,php,',
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $root = $directory->url();

        $this->assertRegExp('/Ignore:.+txt,php/', $output);
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
                    'subfile.txt' => 'content',
                    'myimage.jpg' => 'image'
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
        $this->assertFileNotExists($root . '/destination/sub/subfile.txt');
        $this->assertFileExists($root . '/destination/sub/myimage.jpg');
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

            '--format' => ':name',
            '-r' => true
        ]);

        $root = $directory->url();

        $this->assertFileExists($root . '/destination/myfile.jpg');
        $this->assertFileExists($root . '/destination/myfile (1).jpg');
        $this->assertFileExists($root . '/destination/myfile (2).jpg');
        $this->assertFileExists($root . '/destination/myFile (1).jpg');
        $this->assertFileExists($root . '/destination/myFile (1) (1).jpg');
        $this->assertFileNotExists($root . '/destination/noext');
        $this->assertFileNotExists($root . '/destination/noext (1)');
    }

    /**
     * This doesnt work with vfs.. For know we just expect to crash
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

        $this->expectException(\PHPUnit\Framework\Error\Warning::class);
        $this->expectExceptionMessage('link(): No such file or directory');

        $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',

            '--link' => true
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'interactive' => true], ['Yes']);
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

        $this->assertContains('- ' . $directory->url() . '/source/myfile.jpg', $output);
    }

    public function testInteractive()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'content',
                'skip' => [
                    'tobeskipped.jpg' => 'content2'
                ]
            ],
            'destination' => [

            ]
        ]);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',

            '-r' => true
        ], ['interactive' => true], ['Yes', 'N']);

        $this->assertContains('Move file? (yes/no) [yes]', $output);

        // Answer: yes, move
        $this->assertFileExists($directory->url() . '/destination/myfile.jpg');

        // Answer: N, Left unchanged
        $this->assertFileNotExists($directory->url() . '/destination/skip/tobeskipped.jpg');
        $this->assertDirectoryNotExists($directory->url() . '/destination/skip');
        $this->assertContains('content2', file_get_contents($directory->url() . '/source/skip/tobeskipped.jpg'));
    }

    public function testFailingFilenameFormatPattern()
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
            '--format' => ':crash'
        ], ['interactive' => false]);

        $this->assertFileExists($directory->url() . '/source/myfile.jpg');
        $this->assertFileExists($directory->url() . '/source/other');
        $this->assertFileNotExists($directory->url() . '/destination/other');

        $output = $commandTester->getDisplay();
        $this->assertContains('The format: [:crash] failed with message: I am a crasher!', $output);
    }

    public function testByType()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => file_get_contents(__DIR__ . '/../exif.jpg'),
                'other' => file_get_contents(__DIR__ . '/../exif.jpg'),
                'myvideo.3gp' => 'video',
                'myaudio.au' => '',
                'invalid.txt' => 'nope'
            ],
            'destination' => [

            ]
        ]);

        $this->commandTester->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
            '--only-type' => 'image',
        ], ['interactive' => false]);

        $this->assertFileExists($directory->url() . '/destination/myfile.jpg');
        $this->assertFileExists($directory->url() . '/destination/other');
        $this->assertFileExists($directory->url() . '/source/invalid.txt');
        $this->assertFileExists($directory->url() . '/source/myvideo.3gp');
        $this->assertFileExists($directory->url() . '/source/myaudio.au');

        $this->commandTester->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
            '--only-type' => 'video',
        ], ['interactive' => false]);

        $this->assertFileExists($directory->url() . '/destination/myvideo.3gp');
        $this->assertFileExists($directory->url() . '/source/myaudio.au');

        $this->commandTester->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
            '--only-type' => 'audio',
        ], ['interactive' => false]);

        $this->assertFileExists($directory->url() . '/destination/myaudio.au');

         $this->commandTester->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
            '--only-type' => '',
        ], ['interactive' => false]);

        $this->assertContains("Missing value for --only-type", $this->commandTester->getDisplay());
    }

    public function testDryRun()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'content',
                'nested' => [
                    'other' => 'content2'
                ]
            ],
            'destination' => [

            ]
        ]);

        $this->commandTester->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
            '--dry-run' => true,
            '-r' => true,
        ], ['interactive' => false]);

        $this->assertFileExists($directory->url() . '/source/myfile.jpg');
        $this->assertFileExists($directory->url() . '/source/nested/other');
        $this->assertDirectoryNotExists($directory->url() . '/destination/nested');
    }

    public function testSourceFileRemovedAfterStart()
    {
        $application = new Application();

        /** @var Command $command */
        $command = $application->find('move');

        $commandTester = new CommandTester($command);

        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'content',
                'other.jpg' => 'content2'
            ],
            'destination' => [

            ]
        ]);

        $command->subscribe('iterate.start', function (string $sourceFilePath) use ($directory) {
            if ($sourceFilePath === $directory->url() . '/source/myfile.jpg') {
                unlink($sourceFilePath);
            }
        });

        $commandTester->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
        ], ['interactive' => false]);

        $this->assertFileExists($directory->url() . '/destination/other.jpg');
    }

    public function testDestinationNotReadable()
    {
        $directory = $this->createDirectory([
            'source' => [
                    'myfile.jpg' => 'content',
            ],
            'destination' => [
            ]
        ]);

        chmod($directory->url() . '/destination/', 0500);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
        ]);

        $this->assertFileExists($directory->url() . '/source/myfile.jpg');
    }

    public function testDestinationNotWritable()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'content',
            ],
            'destination' => [
            ]
        ]);

        chmod($directory->url() . '/destination/', 0500);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
        ]);

        $this->assertFileExists($directory->url() . '/source/myfile.jpg');
    }

    public function testDestinationFileNotWritable()
    {
        $directory = $this->createDirectory([
            'source' => [
                'nested' => [
                    'myfile.jpg' => 'content',
                ],

                'sub' => [
                    'other.jpg' => 'other'
                ]
            ],
            'destination' => [
                'nested' => [

                ]
            ]
        ]);

        chmod($directory->url() . '/destination/nested', 0500);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
            '-r' => true
        ]);

        $this->assertFileExists($directory->url() . '/source/nested/myfile.jpg');
        $this->assertFileExists($directory->url() . '/destination/sub/other.jpg');
    }

    public function testDestinationSubDirectoryNotWritable()
    {
        $directory = $this->createDirectory([
            'source' => [
                'nested' => [
                    'nested' => [
                        'myfile.jpg' => 'content',
                    ]
                ],

                'sub' => [
                    'other.jpg' => 'other'
                ]
            ],
            'destination' => [
                'nested' => [

                ]
            ]
        ]);

        chmod($directory->url() . '/destination/nested', 0500);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
            '-r' => true
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertContains('Skipped: Not writable ('.$directory->url().'/destination/nested/nested/myfile.jpg)', $output);
        $this->assertFileExists($directory->url() . '/source/nested/nested/myfile.jpg');
        $this->assertFileExists($directory->url() . '/destination/sub/other.jpg');
    }

    public function testSourceNotReadable()
    {
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'content',
            ],
            'destination' => [
            ]
        ]);

        chmod($directory->url() . '/source/', 0200);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
        ]);

        $this->assertContains('Source is not readable', $output);
        $this->assertFileExists($directory->url() . '/source/myfile.jpg');
    }

    public function testNestedSourceDirectoryNotReadable()
    {
        $directory = $this->createDirectory([
            'source' => [
                'nested' => [
                    'myfile.jpg' => 'content',
                ]
            ],
            'destination' => [

            ]
        ]);

        chmod($directory->url() . '/source/nested', 0200);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
            '-r' => true
        ]);

        $this->assertFileExists($directory->url() . '/source/nested/myfile.jpg');
    }

    public function testNoSourceFileNotReadable()
    {
        $directory = $this->createDirectory([
            'source' => [
                'nested' => [
                    'myfile.jpg' => 'content',
                ]
            ],
            'destination' => [

            ]
        ]);

        chmod($directory->url() . '/source/nested/myfile.jpg', 0200);

        $output = $this->execute([
            'source' => $directory->url() . '/source',
            'destination' => $directory->url() . '/destination',
            '-r' => true
        ]);

        $this->assertFileExists($directory->url() . '/source/nested/myfile.jpg');
    }

    public function testCannotRenameAcrossWrapperTypes()
    {
        // Should stop completely
        $directory = $this->createDirectory([
            'source' => [
                'myfile.jpg' => 'content',
                'other' => 'content2'
            ],
            'destination' => [

            ]
        ]);

        $output = $this->execute([
            'source' => __DIR__ . '/../',
            'destination' => $directory->url() . '/destination',
        ]);

        $this->assertContains('PrettyTree doesn\'t support operations across wrapper types', $output);

        $output = $this->execute([
            'destination' => __DIR__ . '/../',
            'source' => $directory->url() . '/destination',
        ]);

        $this->assertContains('PrettyTree doesn\'t support operations across wrapper types', $output);
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
