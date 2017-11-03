<?php

namespace Eigan\Mediasort;

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

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

        $this->getDefinition()->addOption(
            new InputOption('--no-exif', '', InputOption::VALUE_NONE, 'Disable exif requirement')
        );
    }

    /**
     * Get the formatter used for filename formatting
     *
     * @return FilenameFormatter
     */
    public function getFilenameFormatter(): FilenameFormatter
    {
        return $this->formatter;
    }

    public function shouldUseExif(InputInterface $input)
    {
        if (function_exists('exif_read_data') === false || $input->getOption('no-exif')) {
            return false;
        }

        return true;
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
