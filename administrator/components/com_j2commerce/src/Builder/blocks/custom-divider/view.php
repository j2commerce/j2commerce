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
$style     = htmlspecialchars($settings['style'] ?? 'solid', ENT_QUOTES, 'UTF-8');
$thickness = htmlspecialchars($settings['thickness'] ?? '1px', ENT_QUOTES, 'UTF-8');
$color     = htmlspecialchars($settings['color'] ?? '#dee2e6', ENT_QUOTES, 'UTF-8');
?>
<hr style="border-style:<?php echo $style; ?>; border-width:<?php echo $thickness; ?>; border-color:<?php echo $color; ?>; border-bottom:none;" />
