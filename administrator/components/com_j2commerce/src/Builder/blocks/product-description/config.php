<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

return [
    'slug'     => 'product-description',
    'label'    => 'Product Description',
    'icon'     => 'fa-solid fa-align-left',
    'category' => 'Product',
    'settings' => [
        'max_chars' => ['type' => 'text', 'label' => 'Max Characters (0 = unlimited)', 'default' => '150'],
        'css_class' => ['type' => 'text', 'label' => 'CSS Classes', 'default' => 'j2commerce-product-description mb-3'],
    ],
];
