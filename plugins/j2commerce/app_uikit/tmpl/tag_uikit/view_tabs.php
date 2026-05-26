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
use Joomla\CMS\Language\Text;
?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeProductContent', [$this->item, J2CommerceHelper::utilities()->getContext('view_content')])->getArgument('html', ''); ?>
	<div class="uk-grid" uk-grid>
		<div class="uk-width-1-1">
			<?php
				$hasShortDesc = $this->params->get('item_show_sdesc', 1) && !empty(trim(strip_tags($this->item->product_short_desc ?? '')));
				$hasLongDesc  = $this->params->get('item_show_ldesc', 1) && !empty(trim(strip_tags($this->item->product_long_desc ?? '')));
				$hasDescription = $hasShortDesc || $hasLongDesc;
				$set_specification_active = !$hasDescription;
			?>
			<ul uk-tab>
				<?php if($hasDescription): ?>
					<li class="uk-active"><a href="#"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_DESCRIPTION')?></a></li>
				<?php endif; ?>

				<?php if($this->params->get('item_show_product_specification', 0)): ?>
					<li<?php echo isset($set_specification_active) && $set_specification_active ? ' class="uk-active"' : ''; ?>><a href="#"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_SPECIFICATIONS')?></a></li>
				<?php endif; ?>
			</ul>

			<ul class="uk-switcher uk-margin">
				<?php if($hasDescription): ?>
				<li>
					<?php echo $this->loadTemplate('sdesc'); ?>
					<?php echo $this->loadTemplate('ldesc'); ?>
				</li>
				<?php endif; ?>

				<?php if($this->params->get('item_show_product_specification', 0)): ?>
				<li<?php echo isset($set_specification_active) && $set_specification_active ? ' class="uk-active"' : ''; ?>>
					<?php echo $this->loadTemplate('specs'); ?>
				</li>
				<?php endif; ?>
			</ul>

		</div>
	</div>
