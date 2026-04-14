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

$settings = $settings ?? [];
$cssClass = $settings['css_class'] ?? '';
?>
<div data-j2c-block="custom-html"<?php if ($cssClass): ?> class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?> contenteditable="true">
    <p class="text-muted small">Custom HTML content — double-click to edit.</p>
</div>
