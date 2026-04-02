<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

return [
    'slug'     => 'custom-text',
    'label'    => 'Text Block',
    'icon'     => 'fa-solid fa-align-left',
    'category' => 'Custom Content',
    'settings' => [
        'css_class'  => ['type' => 'text', 'label' => 'CSS Classes', 'default' => ''],
        'text_align' => ['type' => 'select', 'label' => 'Text Align', 'default' => 'left', 'options' => ['left', 'center', 'right']],
    ],
];
