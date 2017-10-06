<?php

namespace Eig\PrettyTree\Subscribers;

use Eig\PrettyTree\OutputStyle;
use Symfony\Component\Console\Input\InputInterface;

class VerboseSubscriber
{
    /**
     * @var OutputStyle
     */
    private $io;

    public function __construct(OutputStyle $io)
    {
        $this->io = $io;
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

                $this->io->text("Format:\t\"$format\"");
                $this->io->text("Use hardlink:\t$shouldLink");
                $this->io->text("Recursive:\t$recursive");
                if ($ignore) {
                    $this->io->text("Ignore:\t$ignore");
                }

                $this->io->text("Dry run:\t$dryRyn");

                $this->io->newLine();
            },

            'paths.resolved' => function (string $source, string $destination) {
                $this->io->text("Source: $source");
                $this->io->text("Destination: $destination");
                $this->io->newLine();
            },

            'iterate.destinationDuplicate' => function (string $sourceFile, string $destinationFile) {
                $this->io->writeln("<fg=yellow> Skipped: Duplicate ($destinationFile)</>");
            },

            'iterate.destinationNotWritable' => function (string $destinationFile) {
                $this->io->writeln("<fg=yellow> Skipped: Not writable ($destinationFile)");
            }
        ];
    }
}
