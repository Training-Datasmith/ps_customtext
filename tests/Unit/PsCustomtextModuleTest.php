<?php

class PsCustomtextModuleTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        PrestaShopHarness::resetAll();
    }

    protected function tearDown()
    {
        PrestaShopHarness::resetAll();
    }

    public function testConstructorMetadata()
    {
        $module = new Ps_Customtext();
        $this->assertSame('ps_customtext', $module->name);
        $this->assertSame('front_office_features', $module->tab);
        $this->assertNotEmpty($module->version);
        $xml = simplexml_load_file(dirname(dirname(__DIR__)) . '/config.xml');
        $this->assertSame((string) $xml->version, $module->version);
        $this->assertSame('1.7.5.0', $module->ps_versions_compliancy['min']);
        $this->assertTrue(in_array(array('info', array('type' => 'shop')), PrestaShopHarness::$tableAssociations, true));
    }

    public function testModule16Constant()
    {
        $this->assertSame('blockcmsinfo', Ps_Customtext::MODULE_16);
    }

    public function testGuardExitsWithoutPrestaShopVersion()
    {
        $repo = dirname(dirname(__DIR__));
        $cmd = 'php -r ' . escapeshellarg(
            'echo "BEFORE\n"; include ' . var_export($repo . '/ps_customtext.php', true) . '; echo "AFTER\n";'
        );
        $output = array();
        $exitCode = 0;
        exec($cmd . ' 2>&1', $output, $exitCode);
        $joined = implode("\n", $output);
        $this->assertSame(0, $exitCode);
        $this->assertContains('BEFORE', $joined);
        $this->assertNotContains('AFTER', $joined);
    }

    public function testInstallWithoutLegacyModule()
    {
        PrestaShopHarness::$moduleManagerInstalled = array('blockcmsinfo' => false);
        $module = new Ps_Customtext();
        $this->assertTrue($module->install());
        $this->assertContains('displayHome', PrestaShopHarness::$registeredHooks);
        $this->assertContains('actionShopDataDuplication', PrestaShopHarness::$registeredHooks);
        $this->assertGreaterThan(0, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'info`'));
    }

    public function testInstallStopsWhenParentInstallFails()
    {
        PrestaShopHarness::$moduleInstallResult = false;
        $module = new Ps_Customtext();
        $this->assertFalse($module->install());
        $this->assertSame(0, (int) Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ps_info'"
        ));
    }

    public function testInstallFrom16MigratesData()
    {
        PrestaShopHarness::$moduleManagerInstalled = array('blockcmsinfo' => true);
        PrestaShopHarness::createOldSchemaRow(1, 1, 'Legacy text');
        $old = new FakeBlockCmsInfoModule();
        PrestaShopHarness::$moduleInstances['blockcmsinfo'] = $old;

        $module = new Ps_Customtext();
        $this->assertTrue($module->install());

        $id = CustomText::getCustomTextIdByShop(1);
        $this->assertNotFalse($id);
        $loaded = new CustomText($id, 1, 1);
        $this->assertSame('Legacy text', $loaded->text);
        $this->assertTrue($old->uninstallCalled);
    }

    public function testUninstall()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        $this->assertTrue($module->uninstall());
        $count = (int) Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ps_info'"
        );
        $this->assertSame(0, $count);
    }

    public function testUninstallFailsWhenParentFails()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        PrestaShopHarness::$moduleUninstallResult = false;
        $this->assertFalse($module->uninstall());
        $count = (int) Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ps_info'"
        );
        $this->assertSame(1, $count);
    }

    public function testInstallFixturesSingleShop()
    {
        PrestaShopHarness::$shopList = array(1 => 1);
        $module = new Ps_Customtext();
        $module->installDB();
        $this->assertTrue($module->installFixtures());
        $id = CustomText::getCustomTextIdByShop(1);
        $info = new CustomText($id);
        foreach (PrestaShopHarness::$languages as $lang) {
            $this->assertNotEmpty($info->text[$lang['id_lang']]);
        }
    }

    public function testInstallFixturesTwoShops()
    {
        PrestaShopHarness::$shopList = array(1 => 1, 2 => 2);
        $module = new Ps_Customtext();
        $module->installDB();
        $this->assertTrue($module->installFixtures());
        $id2 = CustomText::getCustomTextIdByShop(2);
        $this->assertNotFalse($id2);
        $info2 = new CustomText($id2, 1, 2);
        $this->assertNotEmpty($info2->text);
    }

    public function testInstallFixturesStopsWhenAddFails()
    {
        PrestaShopHarness::$shopList = array(1 => 1, 2 => 2);
        PrestaShopHarness::$forceAddResult = false;
        $module = new Ps_Customtext();
        $module->installDB();
        $this->assertFalse($module->installFixtures());
    }

    public function testHookActionShopDataDuplication()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        PrestaShopHarness::$shopContextId = 1;
        $info = new CustomText();
        $info->text = array(1 => 'From old', 2 => 'From old');
        $info->add();

        $module->hookActionShopDataDuplication(array(
            'cookie' => new Cookie(),
            'cart' => new Cart(),
            'altern' => 1,
            'old_id_shop' => 1,
            'new_id_shop' => 2,
        ));

        $id2 = CustomText::getCustomTextIdByShop(2);
        $loaded = new CustomText($id2, 1, 2);
        $this->assertSame('From old', $loaded->text);
    }

    public function testHookNoSourceDoesNothing()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        $module->hookActionShopDataDuplication(array(
            'cookie' => new Cookie(),
            'cart' => new Cart(),
            'altern' => 1,
            'old_id_shop' => 1,
            'new_id_shop' => 2,
        ));
        $this->assertSame(0, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'info`'));
    }

    public function testTemplateUsesCmsInfosKey()
    {
        $tpl = file_get_contents(dirname(dirname(__DIR__)) . '/ps_customtext.tpl');
        $this->assertContains('{$cms_infos.text nofilter}', $tpl);
    }
}

class FakeBlockCmsInfoModule
{
    public $uninstallCalled = false;

    public function uninstall()
    {
        $this->uninstallCalled = true;
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'info_lang`');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'info`');

        return true;
    }
}
