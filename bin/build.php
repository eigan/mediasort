<?php

$phar = new Phar("mediasort.phar");

$phar->startBuffering();

$defaultStub = $phar->createDefaultStub("bin/mediasort");

$phar->buildFromIterator(files(), __DIR__ . "/../");

$stub = "#!/usr/bin/env php \n" . $defaultStub;

$phar->setStub($stub);

$phar->stopBuffering();

function files(): \Iterator {
    $iterator = new RecursiveDirectoryIterator(__DIR__ . "/../");

    foreach(new RecursiveIteratorIterator($iterator) as $file) {
        if($file->isDir() === true) {
            continue;
        }

        yield $file;
    }
}