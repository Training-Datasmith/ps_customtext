<?php

class MigrateDataTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        PrestaShopHarness::resetAll();
    }

    protected function tearDown()
    {
        PrestaShopHarness::resetAll();
    }

    public function testRetrieveOldDataMapsSchema()
    {
        PrestaShopHarness::createOldSchemaRow(1, 1, 'Hello');
        PrestaShopHarness::createOldSchemaRow(1, 2, 'Bonjour');
        $db = Db::getInstance();
        $db->execute('INSERT INTO `' . _DB_PREFIX_ . 'info` (`id_shop`) VALUES (2)');
        $id2 = (int) $db->Insert_ID();
        $db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'info_lang` (`id_info`, `id_lang`, `text`) VALUES ('
            . $id2 . ", 1, 'Shop2')"
        );

        $migration = new MigrateData();
        $data = $migration->retrieveOldData();

        $expected = array(
            1 => array(1 => 'Hello', 2 => 'Bonjour'),
            2 => array(1 => 'Shop2'),
        );
        $this->assertEquals($expected, $data);
    }

    public function testRetrieveOldDataEmpty()
    {
        PrestaShopHarness::ensureEmptyOldSchemaTables();
        $migration = new MigrateData();
        $this->assertSame(array(), $migration->retrieveOldData());
    }

    public function testInsertDataWithNoLoadedDataReturnsTrue()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        $migration = new MigrateData();
        $this->assertTrue($migration->insertData());
        $this->assertSame(0, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'info`'));
    }

    public function testInsertDataSingleShop()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        PrestaShopHarness::$shopList = array(2 => 2);
        $migration = new MigrateData();
        $reflection = new ReflectionClass($migration);
        $property = $reflection->getProperty('loadedData');
        $property->setAccessible(true);
        $property->setValue($migration, array(2 => array(1 => 'Only')));
        PrestaShopHarness::$shopContextId = 2;

        $this->assertTrue($migration->insertData());
        $info = new CustomText(CustomText::getCustomTextIdByShop(2), 1, 2);
        $this->assertSame('Only', $info->text);
    }

    public function testInsertDataTwoShops()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        PrestaShopHarness::$shopList = array(1 => 1, 2 => 2);
        $migration = new MigrateData();
        $reflection = new ReflectionClass($migration);
        $property = $reflection->getProperty('loadedData');
        $property->setAccessible(true);
        $property->setValue($migration, array(
            1 => array(1 => 'One'),
            2 => array(1 => 'Two'),
        ));

        $this->assertTrue($migration->insertData());
        $id1 = CustomText::getCustomTextIdByShop(1);
        $id2 = CustomText::getCustomTextIdByShop(2);
        $this->assertNotFalse($id1);
        $info1 = new CustomText($id1, 1, 1);
        $info2 = new CustomText($id2, 1, 2);
        $this->assertSame('One', $info1->text);
        $this->assertSame('Two', $info2->text);
    }

    public function testInsertDataNoActiveShopMatchReturnsTrueWithNoRows()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        PrestaShopHarness::$shopList = array(1 => 1);
        $migration = new MigrateData();
        $reflection = new ReflectionClass($migration);
        $property = $reflection->getProperty('loadedData');
        $property->setAccessible(true);
        $property->setValue($migration, array(99 => array(1 => 'Orphan')));

        $this->assertTrue($migration->insertData());
        $this->assertSame(0, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'info`'));
    }
}
