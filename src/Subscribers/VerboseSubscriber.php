<?php

namespace Eigan\Mediasort\Subscribers;

use Eigan\Mediasort\FilenameFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function exec;

class VerboseSubscriber
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var FilenameFormatter
     */
    private $filenameFormatter;

    public function __construct(OutputInterface $output, FilenameFormatter $filenameFormatter)
    {
        $this->output = $output;
        $this->filenameFormatter = $filenameFormatter;
    }

    public function subscribe(): array
    {
        return [
            'start' => function (InputInterface $input) {
                $format = $input->getOption('format');
                $shouldLink = $input->getOption('link') ? 'true' : 'false';
                $recursive = $input->getOption('recursive') ? 'true' : 'false';
                $ignore = $input->getOption('ignore');
                $dryRyn = $input->getOption('dry-run') ? 'true' : 'false';

                $this->output->writeln("Format:\t\t\"$format\"");
                $this->output->writeln("Use hardlink:\t$shouldLink");
                $this->output->writeln("Recursive:\t$recursive");
                if ($ignore) {
                    $this->output->writeln("Ignore:\t$ignore");
                }

                $this->output->writeln("Dry run:\t$dryRyn");

                if($this->filenameFormatter->useMediaInfo()) {
                    exec("which mediainfo 2>/dev/null", $output, $returnCode);

                    $mediainfo = isset($output[0]) ? $output[0] : 'Unknown';

                    $this->output->writeln("Mediainfo:\t$mediainfo (used as fallback)");
                }

                $this->output->writeln('');
            },

            'paths.resolved' => function (string $source, string $destination) {
                $this->output->writeln("Source:\t\t$source");
                $this->output->writeln("Destination:\t$destination");
                $this->output->writeln('');
            }
        ];
    }
}
