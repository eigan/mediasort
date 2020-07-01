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

    $ignored = ['composer.lock', 'mediasort.gif', 'composer.json', '.php_cs', 'phpunit.xml', 'phpstan.neon', '.phpunit.result.cache'];

    foreach(new RecursiveIteratorIterator($iterator) as $file) {
        if($file->isDir() === true) {
            continue;
        }

        if (strpos($file->getPath(), '/tests') !== false) {
            continue;
        }

        if (strpos($file->getPath(), '/Tests') !== false) {
            continue;
        }

        if (strpos($file->getPath(), '/.git') !== false) {
            continue;
        }

        if (strpos($file->getPath(), '/.idea') !== false) {
            continue;
        }

        if (strpos($file->getPath(), '/demos') !== false) {
            continue;
        }

        if (in_array($file->getFilename(), $ignored)) {
            continue;
        }

        yield $file;
    }
}
