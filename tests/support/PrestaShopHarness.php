<?php

class PrestaShopHarness
{
    public static $moduleInstallResult = true;
    public static $moduleUninstallResult = true;
    public static $registeredHooks = array();
    public static $isCached = false;
    public static $fetchReturn = 'FETCH_SENTINEL';
    public static $getCacheIdCalls = array();
    public static $clearCacheLog = array();
    public static $smartyAssign = array();
    public static $redirectUrl = null;
    public static $moduleManagerInstalled = array();
    public static $moduleInstances = array();
    public static $shopList = array(1 => 1);
    public static $shopContextId = 1;
    public static $shopFeatureActive = false;
    public static $configuration = array();
    public static $languages = array(
        array('id_lang' => 1, 'iso_code' => 'en', 'name' => 'English'),
        array('id_lang' => 2, 'iso_code' => 'fr', 'name' => 'French'),
    );
    public static $toolsValues = array();
    public static $toolsSubmit = array();
    public static $forceAddResult = null;
    public static $forceSaveThrows = false;
    public static $forceSaveResult = null;
    public static $saveCallCount = 0;
    public static $addCallCount = 0;
    public static $tableAssociations = array();
    public static $adminLinkCalls = array();
    public static $helperFormCapture = null;

    public static function bootstrapStubs()
    {
        require_once __DIR__ . '/PrestaShopStubs.php';
    }

    public static function resetAll()
    {
        self::$moduleInstallResult = true;
        self::$moduleUninstallResult = true;
        self::$registeredHooks = array();
        self::$isCached = false;
        self::$fetchReturn = 'FETCH_SENTINEL';
        self::$getCacheIdCalls = array();
        self::$clearCacheLog = array();
        self::$smartyAssign = array();
        self::$redirectUrl = null;
        self::$moduleManagerInstalled = array();
        self::$moduleInstances = array();
        self::$shopList = array(1 => 1);
        self::$shopContextId = 1;
        self::$shopFeatureActive = false;
        self::$configuration = array();
        self::$toolsValues = array();
        self::$toolsSubmit = array();
        self::$forceAddResult = null;
        self::$forceSaveThrows = false;
        self::$forceSaveResult = null;
        self::$saveCallCount = 0;
        self::$addCallCount = 0;
        self::$tableAssociations = array();
        self::$adminLinkCalls = array();
        self::$helperFormCapture = null;
        Context::$instance = null;
        Db::resetInstance();
        self::dropModuleTables();
    }

    public static function dropModuleTables()
    {
        $db = Db::getInstance();
        $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'info_lang`');
        $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'info_shop`');
        $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'info`');
        $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'info_old`');
    }

    public static function ensureEmptyOldSchemaTables()
    {
        self::dropModuleTables();
        $db = Db::getInstance();
        $db->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'info` (
            `id_info` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id_info`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        $db->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'info_lang` (
            `id_info` INT UNSIGNED NOT NULL,
            `id_lang` INT UNSIGNED NOT NULL,
            `text` TEXT NOT NULL,
            PRIMARY KEY (`id_info`, `id_lang`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
    }

    public static function createOldSchemaRow($shopId, $langId, $text)
    {
        $db = Db::getInstance();
        $db->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'info` (
            `id_info` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id_info`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        $db->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'info_lang` (
            `id_info` INT UNSIGNED NOT NULL,
            `id_lang` INT UNSIGNED NOT NULL,
            `text` TEXT NOT NULL,
            PRIMARY KEY (`id_info`, `id_lang`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        $db->execute('INSERT INTO `' . _DB_PREFIX_ . 'info` (`id_shop`) VALUES (' . (int) $shopId . ')');
        $id = (int) $db->Insert_ID();
        $escaped = str_replace("'", "''", $text);
        $db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'info_lang` (`id_info`, `id_lang`, `text`) VALUES ('
            . $id . ', ' . (int) $langId . ", '" . $escaped . "')"
        );
    }
}
