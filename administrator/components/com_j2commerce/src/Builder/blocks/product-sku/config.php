<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

return [
    'slug'     => 'product-sku',
    'label'    => 'Product SKU',
    'icon'     => 'fa-solid fa-barcode',
    'category' => 'Product',
    'settings' => [
        'show_label' => ['type' => 'checkbox', 'label' => 'Show Label', 'default' => true],
        'css_class'  => ['type' => 'text', 'label' => 'CSS Classes', 'default' => 'j2commerce-product-sku small'],
    ],
];
