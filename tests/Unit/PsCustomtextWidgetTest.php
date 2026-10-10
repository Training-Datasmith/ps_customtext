<?php

class PsCustomtextWidgetTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        PrestaShopHarness::resetAll();
        Context::getContext()->shop->id = 1;
        Context::getContext()->language->id = 1;
        $module = new Ps_Customtext();
        $module->installDB();
    }

    protected function tearDown()
    {
        PrestaShopHarness::resetAll();
    }

    public function testGetWidgetVariablesFalseWhenMissing()
    {
        $module = new Ps_Customtext();
        $this->assertSame(false, $module->getWidgetVariables());
    }

    public function testRenderWidgetFalseWhenMissing()
    {
        $module = new Ps_Customtext();
        $this->assertSame(false, $module->renderWidget());
        $this->assertSame(array(), PrestaShopHarness::$smartyAssign);
    }

    public function testGetWidgetVariablesShape()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        $info = new CustomText();
        $info->text = array(1 => 'Block body', 2 => 'Block body');
        $info->add();

        $vars = $module->getWidgetVariables();
        $this->assertInternalType('array', $vars);
        $this->assertArrayHasKey('cms_infos', $vars);
        $this->assertSame('Block body', $vars['cms_infos']['text']);
        $this->assertSame(1, $vars['cms_infos']['id_lang']);
        $this->assertSame(1, $vars['cms_infos']['id_shop']);
        $this->assertArrayNotHasKey('id', $vars['cms_infos']);
    }

    public function testRenderWidgetMissPath()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        $info = new CustomText();
        $info->text = array(1 => 'Block body', 2 => 'Block body');
        $info->add();

        PrestaShopHarness::$isCached = false;
        $result = $module->renderWidget();
        $this->assertSame('FETCH_SENTINEL', $result);
        $this->assertSame('module:ps_customtext/ps_customtext.tpl', $module->lastFetchTemplate);
        $this->assertSame('cache-id-sentinel', $module->lastFetchCacheId);
        $this->assertCount(2, PrestaShopHarness::$getCacheIdCalls);
        $this->assertSame('ps_customtext', PrestaShopHarness::$getCacheIdCalls[0]);
        $this->assertSame('ps_customtext', PrestaShopHarness::$getCacheIdCalls[1]);
    }

    public function testRenderWidgetCachedSkipsLookup()
    {
        $module = new Ps_Customtext();
        PrestaShopHarness::$isCached = true;
        PrestaShopHarness::dropModuleTables();
        $result = $module->renderWidget();
        $this->assertSame('FETCH_SENTINEL', $result);
    }
}
