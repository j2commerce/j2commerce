<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2025 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
?>
<?php if ($this->params->get('item_show_ldesc', 1)) : ?>
	<div class="product-ldesc">
		<?php echo $this->item->product_long_desc; ?>
	</div>
<?php endif; ?>
