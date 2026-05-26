<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeProductTitle', [$this->product, J2CommerceHelper::utilities()->getContext('view_title')])->getArgument('html', ''); ?>

<?php if($this->params->get('item_show_title', 1)): ?>
	<h<?php echo $this->params->get('item_title_headertag', '2'); ?> class="product-title uk-margin-small">
		<?php echo $this->escape($this->product->product_name); ?>
	</h<?php echo $this->params->get('item_title_headertag', '2'); ?>>
<?php endif; ?>
