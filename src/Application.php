<?php

namespace Eigan\Mediasort;

use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
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
        $this->rootPath = $rootPath;
        $this->formatter = new FilenameFormatter();
        
        parent::__construct('Mediasort', self::VERSION);

        $this->setDefaultCommand('move', true);
    }

    /**
     * Get the formatter used for filname formatting
     *
     * @return FilenameFormatter
     */
    public function getFilenameFormatter(): FilenameFormatter
    {
        return $this->formatter;
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultCommands()
    {
        $parent =  parent::getDefaultCommands();

        $own = [
            new Command($this->formatter, $this->rootPath)
        ];

        return array_merge($parent, $own);
    }
}
