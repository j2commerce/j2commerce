<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

return [
    'slug'     => 'product-stock',
    'label'    => 'Stock Status',
    'icon'     => 'fa-solid fa-boxes-stacked',
    'category' => 'Product',
    'settings' => [
        'show_quantity' => ['type' => 'checkbox', 'label' => 'Show Quantity', 'default' => false],
        'css_class'     => ['type' => 'text', 'label' => 'CSS Classes', 'default' => 'j2commerce-product-stock'],
    ],
];
