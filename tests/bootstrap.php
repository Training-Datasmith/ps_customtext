<?php

$repoRoot = dirname(__DIR__);
$fakeModulesDir = $repoRoot . '/tests/support/fake_modules';
if (!is_dir($fakeModulesDir)) {
    mkdir($fakeModulesDir, 0777, true);
}
$moduleLink = $fakeModulesDir . '/ps_customtext';
if (!file_exists($moduleLink)) {
    symlink($repoRoot, $moduleLink);
}

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '1.7.5.1');
}
if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}
if (!defined('_MYSQL_ENGINE_')) {
    define('_MYSQL_ENGINE_', 'InnoDB');
}
if (!defined('_PS_MODULE_DIR_')) {
    define('_PS_MODULE_DIR_', $fakeModulesDir . '/');
}

require_once __DIR__ . '/support/PrestaShopHarness.php';
PrestaShopHarness::bootstrapStubs();

require_once $repoRoot . '/classes/CustomText.php';
require_once $repoRoot . '/classes/MigrateData.php';
require_once $repoRoot . '/ps_customtext.php';
