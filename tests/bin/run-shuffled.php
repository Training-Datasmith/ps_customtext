<?php

$phar = getenv('PHPUNIT_PHAR_PATH') ? getenv('PHPUNIT_PHAR_PATH') : '/tmp/phpunit-4.8.36.phar';
$config = getenv('PHPUNIT_CONFIG') ? getenv('PHPUNIT_CONFIG') : 'phpunit.xml.dist';
$unitDir = dirname(__DIR__) . '/Unit';
$pinned = require dirname(__DIR__) . '/support/pinned-unit-tests.php';

$expectedTotal = 0;
foreach ($pinned as $basename => $count) {
    $expectedTotal += $count;
}
if ($expectedTotal !== 46) {
    fwrite(STDERR, "Pinned test total must be 46, got {$expectedTotal}\n");
    exit(1);
}
if (count($pinned) !== 7) {
    fwrite(STDERR, 'Pinned file list must contain exactly 7 entries, got ' . count($pinned) . "\n");
    exit(1);
}

$filesToRun = array();
foreach ($pinned as $basename => $expectedCount) {
    $path = $unitDir . '/' . $basename;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing pinned test file: {$path}\n");
        exit(1);
    }
    $filesToRun[] = array('path' => $path, 'basename' => $basename, 'expected' => $expectedCount);
}

$order = $filesToRun;
srand(20261008);
shuffle($order);

$ranTotal = 0;
foreach ($order as $entry) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phar)
        . ' -c ' . escapeshellarg($config) . ' ' . escapeshellarg($entry['path']);
    passthru($cmd, $exitCode);
    if ($exitCode !== 0) {
        exit($exitCode);
    }
    $ranTotal += $entry['expected'];
}

if ($ranTotal !== 46) {
    fwrite(STDERR, "Shuffled run pinned count mismatch: ran {$ranTotal}, expected 46\n");
    exit(1);
}

exit(0);
