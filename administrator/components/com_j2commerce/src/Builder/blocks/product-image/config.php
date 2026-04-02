<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

return [
    'slug'     => 'product-image',
    'label'    => 'Product Image',
    'icon'     => 'fa-solid fa-image',
    'category' => 'Product',
    'settings' => [
        'link'         => ['type' => 'checkbox', 'label' => 'Link to Product', 'default' => true],
        'css_class'    => ['type' => 'text', 'label' => 'CSS Classes', 'default' => 'j2commerce-product-image position-relative border mb-3'],
        'aspect_ratio' => ['type' => 'select', 'label' => 'Aspect Ratio', 'default' => 'auto', 'options' => ['auto', '1:1', '4:3', '16:9', '3:4']],
        'object_fit'   => ['type' => 'select', 'label' => 'Object Fit', 'default' => 'cover', 'options' => ['cover', 'contain', 'fill', 'none']],
        'max_height'   => ['type' => 'text', 'label' => 'Max Height', 'default' => '200px'],
    ],
];
