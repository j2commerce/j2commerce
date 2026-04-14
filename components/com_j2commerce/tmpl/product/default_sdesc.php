<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
?>
<?php if ($this->params->get('item_show_sdesc', 1)) : ?>
	<div class="product-sdesc mb-4 mt-2 text-body-emphasis">
		<?php echo $this->item->product_short_desc; ?>
	</div>
<?php endif; ?>
