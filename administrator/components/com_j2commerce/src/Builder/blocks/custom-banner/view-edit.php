<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die;

extract($displayData);

$settings  = $settings ?? [];
$bgColor   = htmlspecialchars($settings['bg_color'] ?? '#f8f9fa', ENT_QUOTES, 'UTF-8');
$textColor = htmlspecialchars($settings['text_color'] ?? '#212529', ENT_QUOTES, 'UTF-8');
$padding   = htmlspecialchars($settings['padding'] ?? '1rem', ENT_QUOTES, 'UTF-8');
$cssClass  = $settings['css_class'] ?? '';
$classAttr = 'j2c-banner' . ($cssClass ? ' ' . htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8') : '');
?>
<div class="<?php echo $classAttr; ?>" data-j2c-block="custom-banner" style="background:<?php echo $bgColor; ?>; color:<?php echo $textColor; ?>; padding:<?php echo $padding; ?>;" contenteditable="true">
    Banner content — double-click to edit.
</div>
