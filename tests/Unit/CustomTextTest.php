<?php

class CustomTextTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        PrestaShopHarness::resetAll();
    }

    protected function tearDown()
    {
        PrestaShopHarness::resetAll();
    }

    public function testDefinitionContract()
    {
        $definition = CustomText::$definition;
        $this->assertSame('info', $definition['table']);
        $this->assertSame('id_info', $definition['primary']);
        $this->assertTrue($definition['multilang']);
        $this->assertTrue($definition['multilang_shop']);
        $this->assertSame(ObjectModel::TYPE_HTML, $definition['fields']['text']['type']);
        $this->assertTrue($definition['fields']['text']['lang']);
        $this->assertTrue($definition['fields']['text']['required']);
        $this->assertSame('isCleanHtml', $definition['fields']['text']['validate']);
    }

    public function testGetCustomTextIdByShopReturnsId()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        $db = Db::getInstance();
        $db->execute('INSERT INTO `' . _DB_PREFIX_ . 'info` (`id_info`) VALUES (5)');
        $db->execute('INSERT INTO `' . _DB_PREFIX_ . 'info_shop` (`id_info`, `id_shop`) VALUES (5, 1)');

        $this->assertSame(5, CustomText::getCustomTextIdByShop(1));
    }

    public function testGetCustomTextIdByShopMissingReturnsFalse()
    {
        $module = new Ps_Customtext();
        $module->installDB();

        $this->assertSame(false, CustomText::getCustomTextIdByShop(9));
    }
}
