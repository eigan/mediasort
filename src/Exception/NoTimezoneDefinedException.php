<?php

namespace Eigan\Mediasort\Exception;

use Eigan\Mediasort\File;
use RuntimeException;
use Throwable;

class NoTimezoneDefinedException extends RuntimeException
{
    /**
     * @var File
     */
    private $sourceFile;

    public function __construct(File $file)
    {
        parent::__construct("No timezone defined for {$file->getPath()}");

        $this->sourceFile = $file;
    }

    public function getSourceFile(): File
    {
        return $this->sourceFile;
    }
}
