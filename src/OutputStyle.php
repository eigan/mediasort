<?php

namespace Eig\PrettyTree;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class OutputStyle extends SymfonyStyle
{
    protected $input;

    protected $output;

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        parent::__construct($input, $output);

        $this->input = $input;
        $this->output = $output;
    }
    
    public function linkPath($path)
    {
        return $this->writeln("   $path");
    }

    public function movePath($path)
    {
        return $this->writeln(" <fg=red>- $path</> (move)");
    }

    public function destinationPath($path)
    {
        return $this->writeln(" <fg=green>+ $path</>");
    }
}
