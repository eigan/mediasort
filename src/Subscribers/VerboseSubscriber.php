<?php

namespace Eigan\Mediasort\Subscribers;

use Closure;
use Eigan\Mediasort\FilenameFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function exec;
use function is_string;

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

    /**
     * @return array<string, Closure>
     */
    public function subscribe(): array
    {
        return [
            'start' => function (InputInterface $input) {
                $format = $input->getOption('format');
                $shouldLink = $input->getOption('link') ? 'true' : 'false';
                $recursive = $input->getOption('recursive') ? 'true' : 'false';
                $ignore = $input->getOption('ignore');
                $dryRyn = $input->getOption('dry-run') ? 'true' : 'false';

                if (is_string($format)) {
                    $this->output->writeln("Format:\t\t\"$format\"");
                }

                $this->output->writeln("Use hardlink:\t$shouldLink");
                $this->output->writeln("Recursive:\t$recursive");

                if (is_string($ignore)) {
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
