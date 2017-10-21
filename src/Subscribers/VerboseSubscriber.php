<?php

namespace Eigan\Mediasort\Subscribers;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VerboseSubscriber
{
    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
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

                $this->output->writeln("Format:\t\"$format\"");
                $this->output->writeln("Use hardlink:\t$shouldLink");
                $this->output->writeln("Recursive:\t$recursive");
                if ($ignore) {
                    $this->output->writeln("Ignore:\t$ignore");
                }

                $this->output->writeln("Dry run:\t$dryRyn");

                $this->output->writeln('');
            },

            'paths.resolved' => function (string $source, string $destination) {
                $this->output->writeln("Source: $source");
                $this->output->writeln("Destination: $destination");
                $this->output->writeln('');
            },

            'iterate.destinationDuplicate' => function (string $sourceFile, string $destinationFile) {
                $this->output->writeln("<fg=yellow> Skipped: Duplicate ($destinationFile)</>");
            },

            'iterate.destinationNotWritable' => function (string $destinationFile) {
                $this->output->writeln("<fg=yellow> Skipped: Not writable ($destinationFile)");
            }
        ];
    }
}
