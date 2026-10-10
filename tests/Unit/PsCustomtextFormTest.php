<?php

class PsCustomtextFormTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        PrestaShopHarness::resetAll();
        PrestaShopHarness::$configuration['PS_LANG_DEFAULT'] = 1;
        Context::getContext()->shop->id = 3;
        PrestaShopHarness::$shopContextId = 3;
        $module = new Ps_Customtext();
        $module->installDB();
    }

    protected function tearDown()
    {
        PrestaShopHarness::resetAll();
    }

    public function testProcessSaveUsesPostedShops()
    {
        PrestaShopHarness::$toolsValues = array(
            'checkBoxShopAsso_configuration' => array(1, 2),
            'text_1' => 'Alpha',
            'text_2' => 'Beta',
        );
        $module = new Ps_Customtext();
        $module->installDB();
        $this->assertTrue($module->processSaveCustomText());
        $id1 = CustomText::getCustomTextIdByShop(1);
        $id2 = CustomText::getCustomTextIdByShop(2);
        $this->assertNotFalse($id1);
        $this->assertNotFalse($id2);
        $one = new CustomText($id1);
        $two = new CustomText($id2);
        $this->assertSame('Alpha', $one->text[1]);
        $this->assertSame('Beta', $two->text[2]);
    }

    public function testProcessSaveRejectsIframeWhenDisabled()
    {
        PrestaShopHarness::$configuration['PS_ALLOW_HTML_IFRAME'] = false;
        PrestaShopHarness::$toolsValues = array(
            'text_1' => 'ok',
            'text_2' => '<iframe src="https://example.com/embed"></iframe>',
        );
        $module = new Ps_Customtext();
        $module->installDB();
        $this->assertFalse($module->processSaveCustomText());
        $this->assertSame(0, (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'info`'));
        $this->assertNotEmpty(Context::getContext()->controller->errors);
    }

    public function testProcessSaveAllowsIframeWhenEnabled()
    {
        PrestaShopHarness::$configuration['PS_ALLOW_HTML_IFRAME'] = true;
        PrestaShopHarness::$toolsValues = array(
            'text_1' => 'ok',
            'text_2' => '<iframe src="https://example.com/embed"></iframe>',
        );
        $module = new Ps_Customtext();
        $module->installDB();
        $this->assertTrue($module->processSaveCustomText());
    }

    public function testProcessSaveHandlesException()
    {
        PrestaShopHarness::$toolsValues = array('text_1' => 'a', 'text_2' => 'b', 'checkBoxShopAsso_configuration' => array(1, 2));
        PrestaShopHarness::$forceSaveThrows = true;
        $module = new Ps_Customtext();
        $module->installDB();
        $this->assertFalse($module->processSaveCustomText());
    }

    public function testGetContentValidationError()
    {
        PrestaShopHarness::$toolsSubmit['saveps_customtext'] = true;
        PrestaShopHarness::$toolsValues['text_1'] = '';
        $module = new Ps_Customtext();
        $output = $module->getContent();
        $this->assertContains('display-error:', $output);
        $this->assertNull(PrestaShopHarness::$redirectUrl);
    }

    public function testGetContentSuccessRedirects()
    {
        PrestaShopHarness::$toolsSubmit['saveps_customtext'] = true;
        PrestaShopHarness::$toolsValues = array('text_1' => 'Saved', 'text_2' => 'Saved');
        $module = new Ps_Customtext();
        $module->installDB();
        try {
            $module->getContent();
            $this->fail('Expected redirect exception');
        } catch (PrestaShopHarnessRedirectException $e) {
            $this->assertNotEmpty(PrestaShopHarness::$adminLinkCalls);
            $params = PrestaShopHarness::$adminLinkCalls[0][1];
            $this->assertSame('ps_customtext', $params['configure']);
            $this->assertSame(4, $params['conf']);
        }
        $this->assertContains('module:ps_customtext/ps_customtext.tpl', PrestaShopHarness::$clearCacheLog);
    }

    public function testGetContentSaveFailureShowsForm()
    {
        PrestaShopHarness::$toolsSubmit['saveps_customtext'] = true;
        PrestaShopHarness::$toolsValues = array('text_1' => 'x', 'text_2' => 'y');
        PrestaShopHarness::$forceSaveResult = false;
        $module = new Ps_Customtext();
        $module->installDB();
        $output = $module->getContent();
        $this->assertNull(PrestaShopHarness::$redirectUrl);
        $this->assertSame(
            0,
            (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'info`')
        );
        $this->assertNotEmpty($output);
    }

    public function testGetFormValuesReturnsMultilingualMap()
    {
        $module = new Ps_Customtext();
        $module->installDB();
        $db = Db::getInstance();
        $db->execute('INSERT INTO `' . _DB_PREFIX_ . 'info` (`id_info`) VALUES (7)');
        $db->execute('INSERT INTO `' . _DB_PREFIX_ . 'info_shop` (`id_info`, `id_shop`) VALUES (7, 3)');
        $db->execute("INSERT INTO `" . _DB_PREFIX_ . "info_lang` (`id_info`, `id_shop`, `id_lang`, `text`) VALUES (7, 3, 1, 'Loaded')");
        $db->execute("INSERT INTO `" . _DB_PREFIX_ . "info_lang` (`id_info`, `id_shop`, `id_lang`, `text`) VALUES (7, 3, 2, 'Charge')");

        $values = $module->getFormValues();
        $this->assertSame(7, $values['id_info']);
        $this->assertInternalType('array', $values['text']);
        $this->assertSame('Loaded', $values['text'][1]);
        $this->assertSame('Charge', $values['text'][2]);
    }

    public function testRenderFormShopWidgetPresence()
    {
        $module = new Ps_Customtext();
        $reflection = new ReflectionClass($module);
        $method = $reflection->getMethod('renderForm');
        $method->setAccessible(true);

        PrestaShopHarness::$shopFeatureActive = false;
        $method->invoke($module);
        $this->assertShopInputAbsent();

        PrestaShopHarness::$helperFormCapture = null;
        PrestaShopHarness::$shopFeatureActive = true;
        $method->invoke($module);
        $this->assertShopInputPresent();
    }

    private function assertShopInputAbsent()
    {
        $inputs = PrestaShopHarness::$helperFormCapture['fields_form'][0]['form']['input'];
        foreach ($inputs as $input) {
            $this->assertNotSame('shop', $input['type']);
        }
    }

    private function assertShopInputPresent()
    {
        $inputs = PrestaShopHarness::$helperFormCapture['fields_form'][0]['form']['input'];
        $found = false;
        foreach ($inputs as $input) {
            if ($input['type'] === 'shop') {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }
}
