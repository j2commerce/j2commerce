<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects
?>
<?php if ($this->params->get('item_show_title', 1)) : ?>
	<h<?php echo $this->params->get('item_title_headertag', '2'); ?> class="product-title mb-3 font-j2commerce text-capitalize"><?php echo $this->escape($this->item->product_name); ?></h<?php echo $this->params->get('item_title_headertag', '2'); ?>>
<?php endif; ?>
