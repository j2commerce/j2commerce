<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

return [
    'slug'     => 'custom-divider',
    'label'    => 'Divider',
    'icon'     => 'fa-solid fa-minus',
    'category' => 'Custom Content',
    'settings' => [
        'style'     => ['type' => 'select', 'label' => 'Border Style', 'default' => 'solid', 'options' => ['solid', 'dashed', 'dotted']],
        'thickness' => ['type' => 'text', 'label' => 'Thickness', 'default' => '1px'],
        'color'     => ['type' => 'color', 'label' => 'Color', 'default' => '#dee2e6'],
    ],
];
