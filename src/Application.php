<?php

namespace Eigan\Mediasort;

use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class Application extends SymfonyApplication
{
    const VERSION = '0.13';

    /**
     * @var string
     */
    private $rootPath;

    /**
     * @var FilenameFormatter
     */
    protected $formatter;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(string $rootPath = '')
    {
        parent::__construct('Mediasort', self::VERSION);

        $this->rootPath = $rootPath;

        $haveMediainfo = $this->haveMediaInfoBin();

        $this->formatter = new FilenameFormatter(true, $haveMediainfo);
        $this->logger = new Logger('mediasort', [new NullHandler()]);

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
        return [
            new HelpCommand(),
            new Command($this->formatter, $this->logger, $this->rootPath)
        ];
    }

    private function haveMediaInfoBin(): bool
    {
        $returnCode = 0;
        $output = "";

        exec("which mediainfo 2>/dev/null", $output, $returnCode);

        return $returnCode === 0;
    }
}
