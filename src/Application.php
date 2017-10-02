<?php

namespace Eig\PrettyTree;

class Application extends \Symfony\Component\Console\Application
{
    const VERSION = 'dev';

    /**
     * @var string
     */
    private $rootPath;

    public function __construct(string $rootPath = '')
    {
        parent::__construct('Uniktree', self::VERSION);

        $this->rootPath = $rootPath;

        $this->setDefaultCommand('move', true);
    }

    protected function getDefaultCommands()
    {
        $parent =  parent::getDefaultCommands();

        $own = [
            new Command($this->rootPath)
        ];

        return array_merge($parent, $own);
    }
}
