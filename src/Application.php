<?php

namespace Eig\PrettyTree;

class Application extends \Symfony\Component\Console\Application
{
    const VERSION = 'dev';

    /**
     * @var string
     */
    private $rootPath;

    /**
     * @var FilenameFormatter
     */
    protected $formatter;

    public function __construct(string $rootPath = '')
    {
        parent::__construct('Uniktree', self::VERSION);

        $this->rootPath = $rootPath;
        $this->formatter = new FilenameFormatter();

        $this->setDefaultCommand('move', true);
    }

    public function getFilenameFormatter(): FilenameFormatter
    {
        return $this->formatter;
    }

    protected function getDefaultCommands()
    {
        $parent =  parent::getDefaultCommands();

        $own = [
            new Command($this->formatter, $this->rootPath)
        ];

        return array_merge($parent, $own);
    }
}
