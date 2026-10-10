<?php

if (!interface_exists('PrestaShop\\PrestaShop\\Core\\Module\\WidgetInterface')) {
    eval('namespace PrestaShop\\PrestaShop\\Core\\Module { interface WidgetInterface {} }');
}
if (!class_exists('PrestaShop\\PrestaShop\\Adapter\\Presenter\\Object\\ObjectPresenter')) {
    eval('namespace PrestaShop\\PrestaShop\\Adapter\\Presenter\\Object {
        class ObjectPresenter {
            public function present($object) { return get_object_vars($object); }
        }
    }');
}
if (!class_exists('PrestaShop\\PrestaShop\\Core\\Addon\\Module\\ModuleManagerBuilder')) {
    eval('namespace PrestaShop\\PrestaShop\\Core\\Addon\\Module {
        class ModuleManagerBuilder {
            private static $instance;
            public static function getInstance() {
                if (!self::$instance) { self::$instance = new self(); }
                return self::$instance;
            }
            public function build() { return new ModuleManager(); }
        }
        class ModuleManager {
            public function isInstalled($name) {
                return !empty(\PrestaShopHarness::$moduleManagerInstalled[$name]);
            }
        }
    }');
}

if (!class_exists('PrestaShopException')) {
    class PrestaShopException extends Exception
    {
    }
}
if (!class_exists('Cookie')) {
    class Cookie
    {
    }
}
if (!class_exists('Cart')) {
    class Cart
    {
    }
}
if (!class_exists('Db')) {
    class Db
    {
        private static $instance;
        private $pdo;

        public static function getInstance()
        {
            if (!self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public static function resetInstance()
        {
            self::$instance = null;
        }

        private function connect()
        {
            if ($this->pdo) {
                return;
            }
            $host = getenv('PS_CUSTOMTEXT_DB_HOST') ? getenv('PS_CUSTOMTEXT_DB_HOST') : '127.0.0.1';
            $port = getenv('PS_CUSTOMTEXT_DB_PORT') ? getenv('PS_CUSTOMTEXT_DB_PORT') : '3306';
            $user = getenv('PS_CUSTOMTEXT_DB_USER') ? getenv('PS_CUSTOMTEXT_DB_USER') : 'ps_customtext';
            $pass = getenv('PS_CUSTOMTEXT_DB_PASSWORD') ? getenv('PS_CUSTOMTEXT_DB_PASSWORD') : 'ps_customtext';
            $db = getenv('PS_CUSTOMTEXT_DB_NAME') ? getenv('PS_CUSTOMTEXT_DB_NAME') : 'ps_customtext_test';
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $db . ';charset=utf8';
            $this->pdo = new PDO($dsn, $user, $pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        }

        public function execute($sql)
        {
            $this->connect();

            return $this->pdo->exec($sql) !== false;
        }

        public function executeS($sql)
        {
            $this->connect();
            $stmt = $this->pdo->query($sql);
            if ($stmt === false) {
                return false;
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $rows === false ? array() : $rows;
        }

        public function getValue($sql)
        {
            $this->connect();
            $stmt = $this->pdo->query($sql);

            return $stmt ? $stmt->fetchColumn() : false;
        }

        public function Insert_ID()
        {
            $this->connect();

            return (int) $this->pdo->lastInsertId();
        }
    }
}
if (!class_exists('Shop')) {
    class Shop
    {
        const CONTEXT_SHOP = 1;
        const CONTEXT_GROUP = 2;
        const CONTEXT_ALL = 3;

        public static function addTableAssociation($table, $definition)
        {
            PrestaShopHarness::$tableAssociations[] = array($table, $definition);
        }

        public static function getShops($active = true, $id_shop_group = null, $get_as_list_id = false)
        {
            return PrestaShopHarness::$shopList;
        }

        public static function setContext($type, $id)
        {
            PrestaShopHarness::$shopContextId = (int) $id;
        }

        public static function getContext()
        {
            return self::CONTEXT_SHOP;
        }

        public static function isFeatureActive()
        {
            return PrestaShopHarness::$shopFeatureActive;
        }

        public static function getTotalShops($active = true)
        {
            return count(PrestaShopHarness::$shopList);
        }
    }
}
if (!class_exists('Tools')) {
    class Tools
    {
        public static function getValue($key, $default = null)
        {
            if (array_key_exists($key, PrestaShopHarness::$toolsValues)) {
                return PrestaShopHarness::$toolsValues[$key];
            }

            return $default;
        }

        public static function isSubmit($key)
        {
            return !empty(PrestaShopHarness::$toolsSubmit[$key]);
        }

        public static function redirectAdmin($url)
        {
            PrestaShopHarness::$redirectUrl = $url;
            throw new PrestaShopHarnessRedirectException($url);
        }

        public static function getAdminTokenLite($controller)
        {
            return 'token-' . $controller;
        }
    }
}
if (!class_exists('Configuration')) {
    class Configuration
    {
        public static function get($key)
        {
            if (array_key_exists($key, PrestaShopHarness::$configuration)) {
                return PrestaShopHarness::$configuration[$key];
            }

            return false;
        }
    }
}
if (!class_exists('Language')) {
    class Language
    {
        public static function getLanguages($active = true)
        {
            return PrestaShopHarness::$languages;
        }
    }
}
if (!class_exists('Link')) {
    class Link
    {
        public function getAdminLink($controller, $ssl = true, $params = array(), $params2 = array())
        {
            PrestaShopHarness::$adminLinkCalls[] = array($controller, $params2);

            return 'http://admin.test/' . $controller;
        }
    }
}
if (!class_exists('Context')) {
    class Context
    {
        public static $instance;
        public $shop;
        public $language;
        public $link;
        public $controller;

        public function __construct()
        {
            $this->shop = new stdClass();
            $this->shop->id = 1;
            $this->language = new stdClass();
            $this->language->id = 1;
            $this->link = new Link();
            $this->controller = new stdClass();
            $this->controller->errors = array();
        }

        public static function getContext()
        {
            if (!self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }
    }
}
if (!class_exists('Module')) {
    class Module
    {
        public $name;
        public $tab;
        public $version;
        public $author;
        public $identifier = 'id_module';
        public $bootstrap;
        public $need_instance;
        public $displayName;
        public $description;
        public $ps_versions_compliancy;
        public $context;
        public $smarty;
        public $lastFetchTemplate;
        public $lastFetchCacheId;

        public function __construct()
        {
            $this->context = Context::getContext();
            $this->smarty = new SmartyStub();
        }

        public function install()
        {
            return PrestaShopHarness::$moduleInstallResult;
        }

        public function uninstall()
        {
            return PrestaShopHarness::$moduleUninstallResult;
        }

        public function registerHook($name)
        {
            PrestaShopHarness::$registeredHooks[] = $name;

            return true;
        }

        public function trans($s, $params = array(), $domain = null)
        {
            return $s;
        }

        public function displayError($message)
        {
            return 'display-error:' . $message;
        }

        protected function _clearCache($template)
        {
            PrestaShopHarness::$clearCacheLog[] = $template;
        }

        public function isCached($template, $cacheId)
        {
            return PrestaShopHarness::$isCached;
        }

        public function getCacheId($name = null)
        {
            PrestaShopHarness::$getCacheIdCalls[] = $name;

            return 'cache-id-sentinel';
        }

        public function fetch($template, $cacheId = null)
        {
            $this->lastFetchTemplate = $template;
            $this->lastFetchCacheId = $cacheId;

            return PrestaShopHarness::$fetchReturn;
        }

        public static function getInstanceByName($name)
        {
            if (isset(PrestaShopHarness::$moduleInstances[$name])) {
                return PrestaShopHarness::$moduleInstances[$name];
            }

            return false;
        }
    }
}
if (!class_exists('SmartyStub')) {
    class SmartyStub
    {
        public function assign($vars)
        {
            PrestaShopHarness::$smartyAssign = $vars;
        }
    }
}
if (!class_exists('HelperForm')) {
    class HelperForm
    {
        public $module;
        public $name_controller;
        public $identifier;
        public $token;
        public $languages = array();
        public $currentIndex;
        public $default_form_language;
        public $allow_employee_form_lang;
        public $toolbar_scroll;
        public $title;
        public $submit_action;
        public $fields_value = array();

        public function generateForm($fields_form)
        {
            PrestaShopHarness::$helperFormCapture = array(
                'fields_form' => $fields_form,
                'fields_value' => $this->fields_value,
            );
            $text = '';
            if (isset($this->fields_value['text'])) {
                $text = is_array($this->fields_value['text'])
                    ? json_encode($this->fields_value['text'])
                    : (string) $this->fields_value['text'];
            }

            return 'FORM:' . $text;
        }
    }
}
if (!class_exists('ObjectModel')) {
    class ObjectModel
    {
        const TYPE_HTML = 'TYPE_HTML';
        const TYPE_NOTHING = 'TYPE_NOTHING';

        public $id;
        public $force_id = false;

        public function __construct($id = null, $id_lang = null, $id_shop = null)
        {
            if ($this instanceof CustomText && $id) {
                $this->id_info = (int) $id;
                $this->loadCustomTextFields($id, $id_lang, $id_shop);
            }
        }

        protected function loadCustomTextFields($id, $id_lang, $id_shop)
        {
            $shopId = $id_shop !== null ? (int) $id_shop : PrestaShopHarness::$shopContextId;
            $db = Db::getInstance();
            if ($id_lang !== null) {
                $sql = 'SELECT `text` FROM `' . _DB_PREFIX_ . 'info_lang` WHERE `id_info` = ' . (int) $id
                    . ' AND `id_shop` = ' . $shopId . ' AND `id_lang` = ' . (int) $id_lang;
                $row = $db->executeS($sql);
                if ($row) {
                    $this->text = $row[0]['text'];
                }

                return;
            }
            $sql = 'SELECT `id_lang`, `text` FROM `' . _DB_PREFIX_ . 'info_lang` WHERE `id_info` = ' . (int) $id
                . ' AND `id_shop` = ' . $shopId;
            $rows = $db->executeS($sql);
            $this->text = array();
            foreach ($rows as $row) {
                $this->text[(int) $row['id_lang']] = $row['text'];
            }
        }

        public function add($autoDate = true, $nullValues = false)
        {
            PrestaShopHarness::$addCallCount++;
            if (PrestaShopHarness::$forceAddResult !== null) {
                return PrestaShopHarness::$forceAddResult;
            }

            return $this->persist('add');
        }

        public function save($nullValues = false, $autoDate = true)
        {
            PrestaShopHarness::$saveCallCount++;
            if (PrestaShopHarness::$forceSaveThrows) {
                throw new PrestaShopException('save failed');
            }
            if (PrestaShopHarness::$forceSaveResult !== null) {
                return PrestaShopHarness::$forceSaveResult;
            }

            return $this->persist('save');
        }

        protected function persist($mode)
        {
            if (!isset($this->text) || $this->text === false || $this->text === '') {
                return false;
            }
            if (is_array($this->text)) {
                $has = false;
                foreach ($this->text as $v) {
                    if ($v !== '' && $v !== false && $v !== null) {
                        $has = true;
                        break;
                    }
                }
                if (!$has) {
                    return false;
                }
            }

                    $db = Db::getInstance();
                    $shopId = PrestaShopHarness::$shopContextId;
                    if ($mode === 'add' || empty($this->id_info)) {
                        $db->execute('INSERT INTO `' . _DB_PREFIX_ . 'info` () VALUES ()');
                        $this->id_info = (int) $db->Insert_ID();
                        $this->id = $this->id_info;
                    } elseif ((int) $db->getValue(
                        'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'info` WHERE `id_info` = ' . (int) $this->id_info
                    ) === 0) {
                        $db->execute(
                            'INSERT INTO `' . _DB_PREFIX_ . 'info` (`id_info`) VALUES (' . (int) $this->id_info . ')'
                        );
                    }
            $db->execute(
                'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'info_shop` (`id_info`, `id_shop`) VALUES ('
                . (int) $this->id_info . ', ' . (int) $shopId . ')'
            );
            $texts = is_array($this->text) ? $this->text : array(1 => $this->text);
            foreach ($texts as $langId => $value) {
                $escaped = str_replace("'", "''", $value);
                $db->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'info_lang` (`id_info`, `id_shop`, `id_lang`, `text`) VALUES ('
                    . (int) $this->id_info . ', ' . (int) $shopId . ', ' . (int) $langId . ", '" . $escaped . "')
                    ON DUPLICATE KEY UPDATE `text` = VALUES(`text`)"
                );
            }

            return true;
        }
    }
}
if (!class_exists('PrestaShopHarnessRedirectException')) {
    class PrestaShopHarnessRedirectException extends Exception
    {
    }
}
