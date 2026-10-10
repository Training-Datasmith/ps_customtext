<?php

class PsCustomtextSchemaTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        PrestaShopHarness::resetAll();
    }

    protected function tearDown()
    {
        PrestaShopHarness::resetAll();
    }

    public function testInstallDbCreatesTables()
    {
        $module = new Ps_Customtext();
        $this->assertTrue($module->installDB());

        $db = Db::getInstance();
        $tables = $db->executeS(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ps_info', 'ps_info_shop', 'ps_info_lang')"
        );
        $this->assertCount(3, $tables);

        $textCol = $db->executeS(
            "SELECT DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ps_info_lang' AND COLUMN_NAME = 'text'"
        );
        $this->assertSame('text', strtolower($textCol[0]['DATA_TYPE']));
        $this->assertSame('NO', $textCol[0]['IS_NULLABLE']);
    }

    public function testInstallDbIsIdempotent()
    {
        $module = new Ps_Customtext();
        $this->assertTrue($module->installDB());
        $this->assertTrue($module->installDB());
    }

    public function testCharsetRoundTrip()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        $unicode = 'café — 日本語 — €';
        PrestaShopHarness::$shopContextId = 1;
        $info = new CustomText();
        $info->text = array(1 => $unicode, 2 => $unicode);
        $this->assertTrue($info->add());

        $loaded = new CustomText((int) $info->id_info, 2, 1);
        $this->assertSame($unicode, $loaded->text);
    }

    public function testUninstallDbDropsTables()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        $this->assertTrue($module->uninstallDB(true));
        $db = Db::getInstance();
        $count = (int) $db->getValue(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'ps_info%'"
        );
        $this->assertSame(0, $count);
    }

    public function testUninstallDbSkipDrop()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        $this->assertTrue($module->uninstallDB(false));
        $db = Db::getInstance();
        $count = (int) $db->getValue(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ps_info'"
        );
        $this->assertSame(1, $count);
    }
}
