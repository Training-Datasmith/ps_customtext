<?php

class UpgradeModule300Test extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        PrestaShopHarness::resetAll();
        require_once dirname(dirname(__DIR__)) . '/upgrade/install-3.0.0.php';
    }

    protected function tearDown()
    {
        PrestaShopHarness::resetAll();
    }

    public function testUpgradeMigratesLegacyData()
    {
        PrestaShopHarness::createOldSchemaRow(1, 1, 'Legacy');
        PrestaShopHarness::$shopList = array(1 => 1);
        $module = new Ps_Customtext();

        $this->assertTrue(upgrade_module_3_0_0($module));
        $this->assertContains('actionShopDataDuplication', PrestaShopHarness::$registeredHooks);
        $id = CustomText::getCustomTextIdByShop(1);
        $loaded = new CustomText($id, 1, 1);
        $this->assertSame('Legacy', $loaded->text);
    }

    public function testUpgradeOrphanShopReturnsTrueWithNoRows()
    {
        PrestaShopHarness::$shopList = array(1 => 1);
        PrestaShopHarness::createOldSchemaRow(99, 1, 'Orphan');
        $module = new Ps_Customtext();
        $db = Db::getInstance();

        $this->assertTrue(upgrade_module_3_0_0($module));
        $this->assertSame(0, (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'info`'));
    }

    public function testUpgradeReturnsFalseWhenUninstallDbFails()
    {
        PrestaShopHarness::ensureEmptyOldSchemaTables();
        $module = new FailingUninstallPsCustomtext();
        $this->assertFalse(upgrade_module_3_0_0($module));
    }
}

class FailingUninstallPsCustomtext extends Ps_Customtext
{
    public function uninstallDB($drop_table = true)
    {
        return false;
    }
}
