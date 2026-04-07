<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

return [
    'slug'     => 'cart-form',
    'label'    => 'Options & Add to Cart',
    'icon'     => 'fa-solid fa-cart-shopping',
    'category' => 'Product',
    'settings' => [
        'show_quantity' => ['type' => 'checkbox', 'label' => 'Show Quantity Input', 'default' => true],
        'btn_class'     => ['type' => 'text', 'label' => 'Button CSS Classes', 'default' => 'btn btn-primary'],
        'css_class'     => ['type' => 'text', 'label' => 'Container CSS Classes', 'default' => 'j2commerce-addtocart-form mt-auto'],
        'btn_text'      => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Add to Cart'],
        'btn_size'      => ['type' => 'select', 'label' => 'Button Size', 'default' => 'default', 'options' => ['btn-sm', 'default', 'btn-lg']],
    ],
];
