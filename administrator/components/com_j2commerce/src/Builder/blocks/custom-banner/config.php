<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

return [
    'slug'     => 'custom-banner',
    'label'    => 'Banner',
    'icon'     => 'fa-solid fa-flag',
    'category' => 'Custom Content',
    'settings' => [
        'bg_color'   => ['type' => 'color', 'label' => 'Background Color', 'default' => '#f8f9fa'],
        'text_color' => ['type' => 'color', 'label' => 'Text Color', 'default' => '#212529'],
        'padding'    => ['type' => 'text', 'label' => 'Padding', 'default' => '1rem'],
        'css_class'  => ['type' => 'text', 'label' => 'CSS Classes', 'default' => ''],
    ],
];
