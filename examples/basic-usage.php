<?php

declare(strict_types=1);

/**
 * Example: Working with the ps_customtext PrestaShop module.
 *
 * ps_customtext allows merchants to add configurable HTML/text blocks to
 * any hook position on the storefront. Text is stored per language in the
 * module configuration and rendered via the widget system.
 *
 * This file documents common usage patterns.
 */

// --- Widget invocation in Smarty/Twig template ---
// {widget name="ps_customtext" hook="displayHome"}

// --- Reading custom text configuration programmatically ---
// The module stores text per language in module configuration:
//
// $idLang = (int) Context::getContext()->language->id;
// $idShop = (int) Context::getContext()->shop->id;
//
// // Key format: CUSTOM_TEXT_{LANG_ID}
// $customText = Configuration::get('CUSTOM_TEXT', $idLang, null, $idShop);
// echo $customText; // Raw HTML content as configured in Back Office

// --- Setting custom text programmatically ---
// Configuration::updateValue(
//     'CUSTOM_TEXT',
//     ['1' => '<p>Welcome to our store!</p>', '2' => '<p>Bienvenidos a nuestra tienda!</p>'],
//     html: true,
//     idShopGroup: null,
//     idShop: $idShop,
// );

// --- Back Office configuration ---
// Modules > Custom Text Block:
//   - Rich text editor per language (HTML allowed)
//   - Hook placement selection
//   - Display per shop in multistore setups

// --- Hook placements ---
// Default: displayHome
// Configurable to: displayFooter, displayLeftColumn, displayRightColumn,
//                  displayTop, displayAfterBodyOpeningTag, etc.

// --- Template override ---
// themes/{theme}/modules/ps_customtext/views/templates/hook/ps_customtext.tpl
