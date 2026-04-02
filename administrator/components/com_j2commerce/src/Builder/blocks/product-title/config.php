<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

return [
    'slug'     => 'product-title',
    'label'    => 'Product Title',
    'icon'     => 'fa-solid fa-heading',
    'category' => 'Product',
    'settings' => [
        'tag'       => ['type' => 'select', 'label' => 'Heading Tag', 'default' => 'h3', 'options' => ['h1','h2','h3','h4','h5','h6','p','span']],
        'link'      => ['type' => 'checkbox', 'label' => 'Link to Product', 'default' => true],
        'css_class' => ['type' => 'text', 'label' => 'CSS Classes', 'default' => 'j2commerce-product-title fs-6'],
        'font_size' => ['type' => 'select', 'label' => 'Font Size', 'default' => 'fs-5', 'options' => ['fs-1','fs-2','fs-3','fs-4','fs-5','fs-6']],
    ],
];
