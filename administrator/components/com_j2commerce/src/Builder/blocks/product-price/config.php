<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

return [
    'slug'     => 'product-price',
    'label'    => 'Product Price',
    'icon'     => 'fa-solid fa-tag',
    'category' => 'Product',
    'settings' => [
        'show_special'    => ['type' => 'checkbox', 'label' => 'Show Special Price', 'default' => true],
        'show_discount'   => ['type' => 'checkbox', 'label' => 'Show Discount Badge', 'default' => true],
        'show_tax_info'   => ['type' => 'checkbox', 'label' => 'Show Tax Info', 'default' => false],
        'css_class'       => ['type' => 'text', 'label' => 'CSS Classes', 'default' => 'j2commerce-product-price'],
        'format'          => ['type' => 'select', 'label' => 'Price Format', 'default' => 'standard', 'options' => ['standard', 'large', 'compact']],
        'show_sale_badge' => ['type' => 'checkbox', 'label' => 'Show Sale Badge', 'default' => true],
    ],
];
