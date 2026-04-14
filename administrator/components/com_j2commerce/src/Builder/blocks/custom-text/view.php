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

$settings   = $settings ?? [];
$cssClass   = $settings['css_class'] ?? '';
$textAlign  = $settings['text_align'] ?? 'left';
$content    = $settings['content'] ?? '';
$styleAttr  = $textAlign !== 'left' ? ' style="text-align:' . htmlspecialchars($textAlign, ENT_QUOTES, 'UTF-8') . ';"' : '';
?>
<p<?php if ($cssClass): ?> class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?><?php echo $styleAttr; ?>><?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?></p>
