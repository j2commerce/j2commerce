<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

return [
    'slug'     => 'product-cart',
    'label'    => 'Add to Cart',
    'icon'     => 'fa-solid fa-cart-shopping',
    'category' => 'Product',
    'settings' => [
        'show_quantity' => ['type' => 'checkbox', 'label' => 'Show Quantity Input', 'default' => true],
        'btn_class'     => ['type' => 'text', 'label' => 'Button CSS Classes', 'default' => 'btn btn-primary'],
        'css_class'     => ['type' => 'text', 'label' => 'Container CSS Classes', 'default' => 'j2commerce-add-to-cart mt-auto'],
    ],
];
