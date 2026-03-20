<?php

declare (strict_types=1);
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
/**
 * Database may change between two version of the module, or between the 1.6 and 1.7 modules.
 * This class allow the data to be kept during upgrades and migrations.
 */
class Migrate_Data
{
    /**
     * @var array
     */
    private $loaded_data = [];
    /**
     * This methods retrieves data from older database models
     * - ps_customtext < v3.0.0
     * - blockcmsinfo (1.6 equivalent module)
     *
     * @return array
     */
    public function retrieve_old_data()
    {
        $this->loaded_data = [];
        $texts = Db::get_instance()->execute_s('SELECT i.`id_shop`, il.`id_lang`, il.`text` FROM `' . _DB_PREFIX_ . 'info` i
            INNER JOIN `' . _DB_PREFIX_ . 'info_lang` il ON il.`id_info` = i.`id_info`');
        if (is_array($texts) && !empty($texts)) {
            foreach ($texts as $text) {
                $this->loaded_data[(int) $text['id_shop']][(int) $text['id_lang']] = $text['text'];
            }
        }
        return $this->loaded_data;
    }
    /**
     * Import the old CustomText data in the new structure
     *
     * @return bool
     */
    public function insert_data()
    {
        if (empty($this->loaded_data)) {
            return true;
        }
        $return = true;
        $shops_ids = Shop::get_shops(true, null, true);
        $custom_texts = array_intersect_key($this->loaded_data, $shops_ids);
        $info = new Custom_Text();
        $info->text = reset($custom_texts);
        $return &= $info->add();
        if (count($custom_texts) > 1) {
            foreach ($custom_texts as $key => $text) {
                Shop::set_context(Shop::CONTEXT_SHOP, (int) $key);
                $info->text = $text;
                $return &= $info->save();
            }
        }
        return (bool) $return;
    }
}