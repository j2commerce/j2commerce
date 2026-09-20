<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;
?>
<?php if ($this->params->get('item_show_product_manufacturer_name', 1) && !empty($this->item->manufacturer)) : ?>
	<span class="manufacturer-brand fs-sm">
		<?php echo Text::_('COM_J2COMMERCE_PRODUCT_MANUFACTURER_NAME'); ?>:
		<?php $url = !empty($this->item->brand_desc_id)
			? J2CommerceHelper::article()->getArticleLink($this->item->brand_desc_id)
			: ''; ?>
		<?php if ($url !== '') : ?>
			<a href="<?php echo $url; ?>" target="_blank">
                <?php echo $this->escape($this->item->manufacturer); ?>
            </a>
		<?php else:?>
			<?php echo $this->escape($this->item->manufacturer); ?>
		<?php endif;?>
	</span>
<?php endif; ?>
