<?php

$phar = getenv('PHPUNIT_PHAR_PATH') ? getenv('PHPUNIT_PHAR_PATH') : '/tmp/phpunit-4.8.36.phar';
$config = getenv('PHPUNIT_CONFIG') ? getenv('PHPUNIT_CONFIG') : 'phpunit.xml.dist';
$files = glob(dirname(__DIR__) . '/Unit/*Test.php');
if ($files === false || count($files) === 0) {
    fwrite(STDERR, "No test files found\n");
    exit(1);
}
srand(20261008);
shuffle($files);
foreach ($files as $file) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phar)
        . ' -c ' . escapeshellarg($config) . ' ' . escapeshellarg($file) . ' ';
    passthru($cmd, $exitCode);
    if ($exitCode !== 0) {
        exit($exitCode);
    }
}
exit(0);
