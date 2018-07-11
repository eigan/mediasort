<?php

namespace Eigan\Mediasort\Exception;

use Eigan\Mediasort\File;
use Exception;

class IncrementedPathIsDuplicate extends Exception
{
    /**
     * @var File
     */
    private $source;

    /**
     * @var string
     */
    private $incrementedPath;

    public function __construct(File $sourceFile, string $incrementedPath)
    {
        parent::__construct('Duplicate file', 0, null);

        $this->source = $sourceFile;
        $this->incrementedPath = $incrementedPath;
    }

    public function getIncrementedPath(): string
    {
        return $this->incrementedPath;
    }
}
